<?php

namespace Madeiranit\RedirectSimpleProducts\Observer;

use Madeiranit\RedirectSimpleProducts\Helper\Data;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Ignore the fucking coding styles
 *
 * @codingStandardsIgnoreFile
 */
class Predispatch implements ObserverInterface
{

    /**
     * @var Http
     */
    protected Http $response;

    /**
     * @var Configurable
     */
    protected Configurable $productTypeConfigurable;

    /**
     * @var ProductRepository
     */
    protected ProductRepository $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var Data
     */
    protected Data $helperData;

    /**
     * @param Http $response
     * @param Configurable $productTypeConfigurable
     * @param ProductRepository $productRepository
     * @param StoreManagerInterface $storeManager
     * @param Data $helperData
     */
    public function __construct (
        Http $response,
        Configurable $productTypeConfigurable,
        ProductRepository $productRepository,
        StoreManagerInterface $storeManager,
        Data $helperData
    ) {
        $this->response = $response;
        $this->productTypeConfigurable = $productTypeConfigurable;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        if (!$this->helperData->getGeneralConfig('enable')) {
            return;
        }

        $request = $observer->getEvent()->getRequest();

        if ($this->isNotACatalogProductViewPath($request)
            || $this->hasNotProductIdInRequest($request)) {
            return;
        }

        $currentProduct = $this->getCurrentProduct($request);
        if (!$this->validateProductAsAChild($currentProduct)) {
            return;
        }

        $parentProductsIds = $this->getParentsProductsIds($currentProduct);
        $storeId = $this->storeManager->getStore()->getId();

        // TODO Validate to use only first result
        foreach ($parentProductsIds as $parentId) {
            try {
                $parentProduct = $this->productRepository->getById($parentId, false, $storeId);
            } catch (NoSuchEntityException $e) {
                continue;
            }

            $commonAttributes = $this->getCommonAttributesBetweenProducts($currentProduct, $parentProduct);
            $urlQueryString = $this->getUrlQueryStringFromRequest($request);
            $urlHashString = $this->getHashStringFromAttributes($commonAttributes);
            $parentProductUrl = $this->getProductUrl($parentProduct);
            $redirectUrl = $parentProductUrl . $urlQueryString . $urlHashString;

            $this->response->setRedirect($redirectUrl, 301);
        }
    }

    /**
     * @param $request
     * @return bool
     */
    protected function isNotACatalogProductViewPath($request): bool
    {
        return strpos($request->getPathInfo(), 'catalog/product/view') === false;
    }

    /**
     * @param $request
     * @return bool
     */
    protected function hasNotProductIdInRequest($request): bool
    {
        return !$request->getParam('id');
    }

    /**
     * @param $request
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    protected function getCurrentProduct($request): ProductInterface
    {
        $productId = $request->getParam('id');
        $storeId = $this->storeManager->getStore()->getId();

        return $this->productRepository->getById($productId, false, $storeId);
    }

    /**
     * @param $product
     * @return bool
     */
    protected function validateProductAsAChild($product): bool
    {
        $productType = $product->getTypeId();

        return $productType === Type::TYPE_SIMPLE || $productType === Type::TYPE_VIRTUAL;
    }

    /**
     * @param ProductInterface $currentProduct
     * @return array
     */
    protected function getParentsProductsIds(ProductInterface $currentProduct): array
    {
        return $this->productTypeConfigurable->getParentIdsByChild($currentProduct->getId());
    }

    /**
     * @param ProductInterface $currentProduct
     * @param ProductInterface $parentProduct
     * @return array
     */
    private function getCommonAttributesBetweenProducts(ProductInterface $currentProduct, ProductInterface $parentProduct): array
    {
        $parentType = $parentProduct->getTypeInstance();
        $parentAttributes = $parentType->getConfigurableAttributesAsArray($parentProduct);

        $options = [];
        foreach ($parentAttributes as $attribute) {
            $code = $attribute['attribute_code'];
            $value = $currentProduct->getData($attribute['attribute_code']);
            $options[$code] = $value;
        }

        return $options;
    }

    /**
     * @param $request
     * @return string
     */
    private function getUrlQueryStringFromRequest($request): string
    {
        $query = $request->getQuery();
        if (is_object($query)) {
            $query = $query->toArray();
        }

        return $query ? '?' . http_build_query($query) : '';
    }

    /**
     * @param array $attributes
     * @return string
     */
    private function getHashStringFromAttributes(array $attributes): string
    {
        return $attributes ? '#' . http_build_query($attributes) : '';
    }

    /**
     * @param ProductInterface $parentProduct
     * @return string
     */
    private function getProductUrl(ProductInterface $parentProduct): string
    {
        $parentRedirectUrl = $parentProduct->getUrlModel()->getUrl($parentProduct);
        $startPathPosition = strpos($parentRedirectUrl, '/', 8) + 1;
        $parentUrlKey = $parentProduct->getUrlKey();

        return substr_replace($parentRedirectUrl, $parentUrlKey, $startPathPosition);
    }
}
