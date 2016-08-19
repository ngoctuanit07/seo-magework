<?php
/**
 * MageWorx
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MageWorx EULA that is bundled with
 * this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.mageworx.com/LICENSE-1.0.html
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the extension
 * to newer versions in the future. If you wish to customize the extension
 * for your needs please refer to http://www.mageworx.com/ for more information
 *
 * @category   MageWorx
 * @package    MageWorx_SeoSuite
 * @copyright  Copyright (c) 2012 MageWorx (http://www.mageworx.com/)
 * @license    http://www.mageworx.com/LICENSE-1.0.html
 */

/**
 * SEO Suite extension
 *
 * @category   MageWorx
 * @package    MageWorx_SeoSuite
 * @author     MageWorx Dev Team
 */
class MageWorx_SeoSuite_Controller_Router extends Mage_Core_Controller_Varien_Router_Standard {

    protected $_request = null;
    protected $_urlData = null;

    public function initControllerRouters($observer) {
        $front = $observer->getEvent()->getFront();
        $router = new MageWorx_SeoSuite_Controller_Router();
        $front->addRouter('seosuite', $router);
    }

    public function match(Zend_Controller_Request_Http $request) {
        $this->_beforeModuleMatch();
        
        if ($this->_matchCategoryPager($request)) {
            return true;
    	}
        
        $this->_setRequest($request);                
        
        if ($this->_matchCategoryLayer()) {
            return true;
        }

        $identifier = trim($request->getPathInfo(), '/');
        
        
        $d = explode('/', $identifier);

        if (count($d) < 2) {
            return false;
        }

        
        
        
        if ('reviews' == $d[1]) {
            $product = Mage::getModel('catalog/product')->loadByAttribute('url_key', $d[0]);
            if (!$product || !$product->getId()) {
                $keyArr = explode('-', $d[0]);
                $productId = end($keyArr);
                if (!is_numeric($productId)) return false;
                $product = Mage::getModel('catalog/product')->load($productId);
                if (!$product || !$product->getId()) return false;                
                $urlArr = explode('/', $product->getProductUrl());
                $urlKey = substr(end($urlArr), 0, -(strlen(Mage::getStoreConfig('catalog/seo/product_url_suffix'))));
                if ($d[0]!=$urlKey) return false;
            }

            if (isset($d[2]) && $d[2] != 'category') {
                if (isset($d[3]) && is_numeric($d[3])) {
                    $reviewId = $d[3];
                }

                $request->setActionName('view')
                        ->setParam('id', $reviewId);
            } else {
                if (isset($d[3])) {
                    $category = Mage::getModel('seosuite/catalog_category')->loadByAttribute('url_key', $d[3]);
                    if ($category && $categoryId = $category->getId()) {
                        $request->setParam('category', $categoryId);
                    }
                }
                $request->setActionName('list')
                        ->setParam('id', $product->getId());
            }

            $request->setModuleName('review')
                    ->setControllerName('product')
                    ->setAlias(
                            Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS, 'reviews'
            );

            return true;
        }
        
        switch ($d[0]) {
            case 'tag':
                if (!isset($d[1])) {
                    return false;
                }
                if (count($d) > 2 || in_array($d[1], array('index', 'customer', 'list'))) {
                    return false;
                }
                $tag = Mage::getModel('tag/tag')->load(urldecode($d[1]), 'name');
                if (!$tag->getId()) {
                    return false;
                }

                if (!$tag->getId() || !$tag->isAvailableInStore())
                    return false;

                $request->setModuleName('tag')->setControllerName('product')->setActionName('list')->setParam('tagId', $tag->getId());

                break;
            case 'rss':
                if (!isset($d[1]) || !isset($d[2])) {
                    return false;
                }
                if (count($d) > 4 || in_array($d[1], array('order'))) {
                    return false;
                }
                $storeId = Mage::app()->getStore($d[1])->getId();
                $t = null;
                if ($d[2]{0} == '@') {
                    $t = substr($d[2], 1);
                }
                switch ($t) {
                    case 'new':
                        $request->setActionName('new')
                                ->setParam('store_id', $storeId);
                        break;
                    case 'specials':
                        $request->setActionName('special')
                                ->setParam('cid', $d[3])
                                ->setParam('store_id', $storeId);
                        break;
                    case 'discounts':
                        $request->setActionName('salesrule')
                                ->setParam('cid', $d[3])
                                ->setParam('store_id', $storeId);
                        break;
                    default:
                        $category = Mage::getModel('seosuite/catalog_category')->setStoreId($storeId)->loadByAttribute('url_key', $d[2]);
                        if (!$category || !$category->getId()) {
                            return false;
                        }
                        $request->setActionName('category')
                                ->setParam('cid', $category->getId())
                                ->setParam('store_id', $storeId);
                }
                $request->setModuleName('rss')
                        ->setControllerName('catalog');
                break;
            default:
                
                // if changed product category -> check product by url_key and redirect to canonicalUrl
                $catRewriteModel = $this->_getCategoryUrlRewrite($identifier); //$identifier
                if (!$catRewriteModel) {
                    $catRewriteModel = $this->_getCategoryUrlRewrite($d[count($d)-1]);
                    if ($catRewriteModel && $catRewriteModel->getProductId()) {                        
                        $product = Mage::getModel('catalog/product')->load($catRewriteModel->getProductId());
                        if ($product && $product->getId()>0) {
                            $canonicalUrl = Mage::helper('seosuite')->getCanonicalUrl($product);
                            if ($canonicalUrl) {
                                //var_dump($canonicalUrl); exit;
                                Mage::app()->getFrontController()->getResponse()->setRedirect($canonicalUrl)->sendResponse(); exit;
                            }
                        }
                        
                        
                    }
                }
                
                return false;
        }
        
        $request->setAlias(Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS, $identifier);
        
        return true;
    }

    protected function _matchCategoryPager($request) {        
        $pagerUrlFormat = Mage::helper('seosuite')->getPagerUrlFormat();
        if (!$pagerUrlFormat) return false;
        
        $url = $request->getRequestUri();
        $suffix = Mage::getStoreConfig('catalog/seo/category_url_suffix');
        $suffixEcaped  = preg_quote($suffix);
        
        $pagerUrlFormatRegEx = explode('[page_number]', $pagerUrlFormat);
        foreach ($pagerUrlFormatRegEx as $key=>$part) {
            $pagerUrlFormatRegEx[$key] = preg_quote($part);
        }
        $pagerUrlFormatRegEx = implode('([0-9]+)', $pagerUrlFormatRegEx);

        if (preg_match('/'.$pagerUrlFormatRegEx.$suffixEcaped.'/', $url, $match)) {
            $url = str_replace($match[0], $suffix, $url);
            $request->setRequestUri($url);

            $path = $request->getPathInfo();
            $path = str_replace($match[0], $suffix, $path);
            $request->setPathInfo($path);
            $request->setParam('p', $match[1]);
        } else {
            return false;
        }
        
        $identifier = trim(str_replace($suffix, '', $request->getPathInfo()), '/');
        $urlSplit = explode('/l/', $identifier, 2);
        if (isset($urlSplit[1])) {
            return false;
        }
        
        Varien_Autoload::registerScope('catalog');
        $productUrl = Mage::getModel('catalog/product_url');
        $cat = $identifier;
        $_params = array();

        $catPath = $cat . $suffix;
        
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();

        $select = $connection->select()->from($tablePrefix . 'core_url_rewrite')->
                        where('request_path = ? AND store_id = ' . Mage::app()->getStore()->getId(), $catPath);
        $rewrite = $connection->fetchRow($select);
        
        $urlRewrite = Mage::getModel('core/url_rewrite')->load($rewrite['url_rewrite_id']);
        if ($urlRewrite && $urlRewrite->getId()) {
            $request->setPathInfo($catPath);
            $request->setModuleName('catalog')
                    ->setControllerName('category')
                    ->setActionName('view')
                    ->setParam('id', $urlRewrite->getCategoryId())
                    ->setAlias(Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS, 'catalog');            
            $urlRewrite->rewrite($request);
            return true;
        }
        return false;
    }
    
    protected function _matchProductAndCategory() {
        list($catPath, $layerParams) = $this->_getUrlData();
        if (!isset($layerParams) || !isset($catPath)) return false;        

        Varien_Autoload::registerScope('catalog');
        $urlRewrite = $this->_getCategoryUrlRewrite($catPath);
        if ($urlRewrite && $urlRewrite->getId()) {
            $this->_prepareRequestForUrlRewrite($urlRewrite, $catPath);

            if (count($layerParams)) {
                $this->_passLayerParamsToRequest($layerParams);
                $urlRewrite->rewrite($this->_getRequest());
                return true;
            }
        }
        return false;
    }
    
    
    protected function _matchCategoryLayer() {
        list($catPath, $layerParams) = $this->_getUrlData();
        if (!isset($layerParams) || !isset($catPath)) return false;        

        Varien_Autoload::registerScope('catalog');
        $urlRewrite = $this->_getCategoryUrlRewrite($catPath);
        if ($urlRewrite && $urlRewrite->getId()) {
            $this->_prepareRequestForUrlRewrite($urlRewrite, $catPath);

            if (count($layerParams)) {
                $this->_passLayerParamsToRequest($layerParams);
                $urlRewrite->rewrite($this->_getRequest());
                return true;
            }
        }
        return false;
    }

    protected function _prepareRequestForUrlRewrite($urlRewrite, $catPath) {
        $this->_getRequest()->setPathInfo($catPath);
        $this->_getRequest()->setModuleName('catalog')
                ->setControllerName('category')
                ->setActionName('view')
                ->setParam('id', $urlRewrite->getCategoryId())
                ->setAlias(
                        Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS, 'catalog'
        );
    }

    protected function _getFilterableCategoryAttributesArray($category) {
        $modelLayer = Mage::getModel('catalog/layer')->setData('current_category', $category);
        $attributes = $modelLayer->getFilterableAttributes();
        $attr = array();

        foreach ($attributes as $attribute) {
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                $attr[] = $option['label'];
            }
        }

        return $attr;
    }

    protected function _isCategoryNameDuplicatesAttribute($category) {
        $attributes = $this->_getFilterableCategoryAttributesArray($category);
        return in_array($category->getName(), $attributes);
    }

    protected function _getCategoryUrlRewrite($catPath) {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
        $select = $connection->select()->from($tablePrefix . 'core_url_rewrite')->where('request_path = ? AND store_id = ' . Mage::app()->getStore()->getId(), $catPath);
        $rewrite = $connection->fetchRow($select);
        if (isset($rewrite['url_rewrite_id'])) return Mage::getModel('core/url_rewrite')->load($rewrite['url_rewrite_id']); else return null;
    }

    protected function _getUrlData() {
        if (!is_null($this->_urlData)) return $this->_urlData;
        $suffix = Mage::getStoreConfig('catalog/seo/category_url_suffix');
        $identifier = trim(preg_replace('/' . preg_quote($suffix, '/') . '$/', '', $this->_getRequest()->getPathInfo()), '/');
        $urlSplit = explode('/l/', $identifier, 2);
        if (!isset($urlSplit[1])) {
            return false;
        }
        $urlSplit[1] = explode('/', $urlSplit[1]);

        $urlSplit[0] .= $suffix;

        $this->_urlData = $urlSplit;

        return $this->_urlData;
    }

    protected function _getCategoryPathInArray() {
        $urlData = $this->_getUrlData();
        return $urlData[1];
        // return explode('/', $urlData[1][0]);
    }

    protected function _getCategoryByParam($param) {
        if (count($param) == 1 && !$this->_getRequest()->getQuery('cat')) {
            $productUrl = Mage::getModel('catalog/product_url');

            $cat = Mage::getModel('seosuite/catalog_category')
                    ->setStoreId(Mage::app()->getStore()->getId())
                    ->loadByAttribute('url_key', $productUrl->formatUrlKey($param[0]));
            if (!$cat) {
                $name = str_replace('-', ' ', $productUrl->formatUrlKey($param[0]));
                $cat = Mage::getModel('seosuite/catalog_category')
                        ->setStoreId(Mage::app()->getStore()->getId())
                        ->loadByAttribute('name', $name);
            }
            if ($cat && $cat->getId() && !in_array($cat->getUrlKey(), $this->_getCategoryPathInArray())) {
                if (!$this->_isCategoryNameDuplicatesAttribute($cat)) {
                    return $cat;
                }
            }
        }

        return false;
    }

    protected function _isHiddenAttribute($param) {
        return count($param) == 1;
    }

    protected function _isHiddenPriceAttribute($param) {
        $localParam = explode(Mage::helper('seosuite')->getAttributeValueDelimiter(), $param[0]);
        return count($localParam) == 2 && $localParam[0] === 'price';
    }

    protected function _setHiddenAttributeToRequest($param) {
        $attr = Mage::helper('seosuite')->_getFilterableAttributes($this->_getCategoryId());
        foreach ($attr as $attrCode => $attrData) {
            if (isset($attrData['options'][$param[0]])) {
                $this->_getRequest()->setQuery($attrCode, $attrData['options'][$param[0]]);
                break;
            }
        }
    }
    
    protected function _setPriceAttributeToRequest($param) {
        $priceParam = explode(Mage::helper('seosuite')->getAttributeValueDelimiter(), $param[0]);
        $this->_getRequest()->setQuery($priceParam[0], $priceParam[1]);
    }

    protected function _setCategoryToRequest($cat) {
        $this->_getRequest()->setQuery('cat', $cat->getName());
    }
    
    protected function _getCategoryId() {        
        $catId = false;
        list($catPath) = $this->_getUrlData();
        if ($catPath) {
            $catRewriteModel = $this->_getCategoryUrlRewrite($catPath);
            if ($catRewriteModel) $catId = $catRewriteModel->getCategoryId();
        }
        return $catId;
    }
    
    protected function _setNotHiddenAttributeToRequest($param) {
        $code = $param[0];
        $value = $param[1];
        if ($code == 'price') {
            $this->_getRequest()->setQuery($code, $value);
        }
                
        $attr = Mage::helper('seosuite')->_getFilterableAttributes($this->_getCategoryId());
        if (isset($attr) && !empty($attr)) {            
            $code = str_replace('-', '_', $code); // attrCode is only = [a-z0-9_]            
            if (isset($attr[$code]) && isset($attr[$code]['options'][$value])) {
                $this->_getRequest()->setQuery($code, $attr[$code]['options'][$value]);
            }
        }
        
    }

    protected function _passLayerParamsToRequest($layerParams) {
        if (empty($layerParams)) return;
        foreach ($layerParams as $params) {
            $param = explode(Mage::helper('seosuite')->getAttributeParamDelimiter(), $params, 2);
            if (empty($param)) continue;
            if ($cat = $this->_getCategoryByParam($param)) {
                $this->_setCategoryToRequest($cat);
            } else if ($this->_isHiddenAttribute($param)) {
                if ($this->_isHiddenPriceAttribute($param)) {
                    $this->_setPriceAttributeToRequest($param);
                } else {
                    $this->_setHiddenAttributeToRequest($param);
                }
            } else {
                $this->_setNotHiddenAttributeToRequest($param);
            }
        }
    }

    protected function _setRequest($request) {
        $this->_request = $request;
    }

    protected function _getRequest() {
        return $this->_request;
    }

}