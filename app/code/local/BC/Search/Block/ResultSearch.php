<?php
class Bc_Search_Block_ResultSearch extends Mage_Catalog_Block_Product_List{
    public function __construct()
    {
        parent::__construct();
        $this->addPriceBlockType('bundle', 'bundle/catalog_product_price', 'bundle/catalog/product/price.phtml');
        $this->addPriceBlockType(
            'giftcard', 'enterprise_giftcard/catalog_product_price', 'giftcard/catalog/product/price.phtml'
        );
        $this->addPriceBlockType('msrp', 'catalog/product_price', 'catalog/product/price_msrp.phtml');
        $this->addPriceBlockType('msrp_item', 'catalog/product_price', 'catalog/product/price_msrp_item.phtml');
        $this->addPriceBlockType('msrp_noform', 'catalog/product_price', 'catalog/product/price_msrp_noform.phtml');
    }

    public function isCanShowAllResultsButton()
    {
        return Mage::helper('search/config')->getInterfaceShowAllResultsButton();
    }

    public function searchProduct($aProductDatas){

        $searchWords = Mage::helper('search')->getSearchedQuery();

        $productids = array();
        $aSearchAttributes = array('name','description','meta_keyword','meta_description');
        foreach($aProductDatas as $product){
            foreach($aSearchAttributes as $attribue){

                if(isset($product[$attribue])){

                    if (strpos(strtolower($product[$attribue]),strtolower($searchWords)) !== false) {
                        $productids[] = $product['entity_id'];
                    }
                }
            }
        }

        $productids = array_unique($productids);
        $aProductCollections= Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*')->addFieldToFilter('entity_id',array('in'=>array($productids)))->setPageSize(Mage::helper('search/config')->getSettingMaxProducts());

        return $aProductCollections;
    }

    public function getItems()
    {

        $cache = Mage::app()->getCache();

        $aProductDatas =  $cache->load("bc_search_ajax");

        if(!$aProductDatas){
            $products_collection = Mage::getModel('catalog/product')->getCollection();

            $aProductDatas = array();
            foreach($products_collection as $prod){
                $product = Mage::getModel('catalog/product')->load($prod->getId());
                $aProductDatas[$prod->getId()] = $product->getData();
            }
            $cache->save(json_encode($aProductDatas), "bc_search_ajax", array("bc_search_ajax"), 60*60);
        }
        else{
            $aProductDatas = json_decode($aProductDatas,true);
        }

        $searchedProductCollection = $this->searchProduct($aProductDatas);

        $result = array();
        if (!is_null($searchedProductCollection)) {

            if (!$thumbnailSize = (int)Mage::helper('search/config')->getSettingThumbnailSize()) {
                $thumbnailSize = 75;
            }

            $searchedWords = Mage::helper('search')->getSearchedWords();
            $itemPattern = Mage::helper('search/config')->getInterfaceItemTemplate();
            $thumbnailUrlPresents = (false !== strpos($itemPattern, '{thumbnail_url}'));

            foreach ($searchedProductCollection as $_product)
            {
                $productUrl = $_product->getProductUrl();
                $priceHTML = $this->getPriceHtml($_product, true);
                $info = $itemPattern;
                foreach (Mage::helper('search')->getUsedAttributes() as $code) {
                    $data = $_product->getData($code);
                    if (!is_string($data)) {
                        continue;
                    }
                    $data = $this->decorateWords($searchedWords, $data, '<strong class="searched-words">', '</strong>');
                    $data = '<div class="std">' . $data . '</div>';
                    $info = str_replace('{' . $code . '}', $data, $info);
                }
                // adding special fields
                $info = str_replace('{price}', '<div class="std">' . $priceHTML . '</div>', $info);
                $info = str_replace('{product_url}', $productUrl, $info);

                if ($thumbnailUrlPresents) {
                    $info = str_replace(
                        '{thumbnail_url}',
                        $_product->getThumbnailUrl($thumbnailSize, $thumbnailSize),
                        $info
                    );
                }

                array_push($result, array(
                    'content' => str_replace('"', '\'', $info),
                    'url' => $productUrl,
                ));
            }
        }



        return $result;
    }

    public function findNearest($haystack, $needles, $offset)
    {
        $haystackL = strtolower($haystack);
        $nearestWord = '';
        $nearestPos = 999999;
        foreach ($needles as $needle)
            if ($needle
                && false !== ($pos = strpos($haystackL, strtolower($needle), $offset))
                && $nearestPos > $pos
            ) {
                $nearestPos = $pos;
                $nearestWord = substr($haystack, $pos, strlen($needle));
            }
        if ($nearestWord) return array('pos' => $nearestPos, 'word' => $nearestWord);
        else return false;
    }

    public function decorateWords($words, $subject, $before, $after)
    {
        $replace = array();
        for ($pos = 0; $pos < strlen($subject) && (false !== $nearest = $this->findNearest($subject, $words, $pos));) {
            $replace[$nearest['pos']] = $nearest['word'];
            $pos = $nearest['pos'] + strlen($nearest['word']);
        }

        $res = '';
        $pos = 0;
        foreach ($replace as $start => $word)
        {
            $res .= substr($subject, $pos, $start - $pos) . $before . $word . $after;
            $pos = $start + strlen($word);
        }
        $res .= substr($subject, $pos);

        return $res;
    }

    public function getResultUrl($query)
    {
        return Mage::helper('catalogsearch')->getResultUrl($query);
    }
}