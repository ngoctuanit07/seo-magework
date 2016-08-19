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

class MageWorx_SeoSuite_Model_Observer {

    public function setMetaDescription(Varien_Event_Observer $observer) {
        $shortDescription = trim($observer->getEvent()->getProduct()->getShortDescription());
        if (Mage::getStoreConfigFlag('mageworx_seo/seosuite/product_meta_description') && !empty($shortDescription)) {
            Mage::getSingleton('catalog/session')->setData('seosuite_meta_description', strip_tags($shortDescription));
        }
    }

    public function registerProductId(Varien_Event_Observer $observer) {
        $product = $observer->getEvent()->getProduct();
        Mage::unregister('seosuite_product_id');
        if ($product) Mage::register('seosuite_product_id', $product->getId());
    }

//    public function convertLayerFilterParams(Varien_Event_Observer $observer) {
//        $action = $observer->getEvent()->getControllerAction();
//        $hlp = Mage::helper('seosuite');
//        $request = $action->getRequest();
//
//        $attributes = Mage::getModel('catalog/layer')->getFilterableAttributes();
//        foreach ($attributes as $attribute) {
//            $attr[$attribute->getAttributeCode()]['type'] = $attribute->getBackendType();
//            $options = $attribute->getSource()->getAllOptions();
//            foreach ($options as $option) {
//                $attr[$attribute->getAttributeCode()]['options'][$hlp->formatUrlKey($option['label'])] = $option['label'];
//            }
//        }
//        if (isset($attr) && !empty($attr)) {
//            Mage::register('_layer_filterable_attributes', $attr);
//                    
//            if (($layerParams = Mage::registry('_layer_params')) && !empty($layerParams)) {
//                foreach ($layerParams as $param) {
//                    foreach ($attr as $attrCode => $attrData) {
//                        if (isset($attrData['options'][$param])) {
//                            $request->setQuery($attrCode, $attrData['options'][$param]);
//                            break;
//                        }
//                    }
//                }
//            }
//            if (($layerParams = $request->getQuery()) && !empty($layerParams)) {
//                foreach ($layerParams as $param => $value) {
//                    if (isset($attr[$param]) && isset($attr[$param]['options'][$value])) {
//                        $request->setQuery($param, $attr[$param]['options'][$value]);
//                    }
//                }
//            }            
//        }
//    }

    public function redirectHome(Varien_Event_Observer $observer) {
        $front = $observer->getEvent()->getFront();
        $origUri = $front->getRequest()->getRequestUri();
        $origUri = explode('?', $origUri, 2);
        $uri = preg_replace('~(?:index\.php/+home/*|index\.php/*|home/*)$~i', '', $origUri[0]);
        if (strpos($origUri[0], '/downloader/index.php') !== false) {
            return;
        }
        if ($uri == $origUri[0]){
            return;
        }
        $uri = rtrim($uri, '/') . '/';
        $uri .= ( isset($origUri[1]) ? '?' . $origUri[1] : '');
        $front->getResponse()
                ->setRedirect($uri)
                ->setHttpResponseCode(301)
                ->sendResponse();
        exit;
    }
    
    public function productSaveAfter(Varien_Event_Observer $observer) {
        if (Mage::helper('seosuite')->getProductReportStatus()) Mage::helper('seosuite')->setProductReportStatus(0);
    }
    public function categorySaveAfter(Varien_Event_Observer $observer) {
        if (Mage::helper('seosuite')->getCategoryReportStatus()) Mage::helper('seosuite')->setCategoryReportStatus(0);
    }
    public function cmsPageSaveAfter(Varien_Event_Observer $observer) {
        if (Mage::helper('seosuite')->getCmsReportStatus()) Mage::helper('seosuite')->setCmsReportStatus(0);
    }
}