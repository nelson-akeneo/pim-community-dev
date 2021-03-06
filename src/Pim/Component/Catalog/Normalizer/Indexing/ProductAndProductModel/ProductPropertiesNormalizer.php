<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Normalizer\Indexing\ProductAndProductModel;

use Pim\Component\Catalog\Model\EntityWithFamilyVariantInterface;
use Pim\Component\Catalog\Model\ProductInterface;
use Pim\Component\Catalog\Model\ProductModelInterface;
use Pim\Component\Catalog\Normalizer\Standard\Product\PropertiesNormalizer as StandardPropertiesNormalizer;
use Pim\Component\Catalog\Repository\ChannelRepositoryInterface;
use Pim\Component\Catalog\Repository\LocaleRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * Transform the properties of products and variant product objects (fields and product values)
 * to the "indexing_product_and_product_model" format.
 *
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductPropertiesNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    private const FIELD_COMPLETENESS = 'completeness';
    private const FIELD_FAMILY_VARIANT = 'family_variant';
    private const FIELD_IN_GROUP = 'in_group';
    private const FIELD_ID = 'id';
    private const FIELD_PARENT = 'parent';
    private const FIELD_ANCESTORS = 'ancestors';
    private const FIELD_CATEGORIES_OF_ANCESTORS = 'categories_of_ancestors';

    /** @var ChannelRepositoryInterface */
    private $channelRepository;

    /** @var LocaleRepositoryInterface */
    private $localeRepository;

    /**
     * @todo merge master: remove "= null"
     */
    public function __construct(
        ChannelRepositoryInterface $channelRepository = null,
        LocaleRepositoryInterface $localeRepository = null
    ) {
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        if (!$this->serializer instanceof NormalizerInterface) {
            throw new \LogicException('Serializer must be a normalizer');
        }

        $data = [];

        $data[self::FIELD_ID] = 'product_' .(string) $product->getId();
        $data[StandardPropertiesNormalizer::FIELD_IDENTIFIER] = $product->getIdentifier();
        $data[StandardPropertiesNormalizer::FIELD_CREATED] = $this->serializer->normalize(
            $product->getCreated(),
            $format
        );
        $data[StandardPropertiesNormalizer::FIELD_UPDATED] = $this->serializer->normalize(
            $product->getUpdated(),
            $format
        );
        $data[StandardPropertiesNormalizer::FIELD_FAMILY] = $this->serializer->normalize(
            $product->getFamily(),
            $format
        );

        $data[StandardPropertiesNormalizer::FIELD_ENABLED] = (bool) $product->isEnabled();
        $data[StandardPropertiesNormalizer::FIELD_CATEGORIES] = $product->getCategoryCodes();
        $ancestorsCategories = [];
        if ($product->isVariant() && null !== $product->getParent()) {
            $ancestorsCategories = $product->getParent()->getCategoryCodes();
        }
        $data[self::FIELD_CATEGORIES_OF_ANCESTORS] = $ancestorsCategories;

        $data[StandardPropertiesNormalizer::FIELD_GROUPS] = $product->getGroupCodes();

        foreach ($product->getGroupCodes() as $groupCode) {
            $data[self::FIELD_IN_GROUP][$groupCode] = true;
        }

        $data[self::FIELD_COMPLETENESS] = !$product->getCompletenesses()->isEmpty()
            ? $this->serializer->normalize(
                $product->getCompletenesses(),
                ProductModelNormalizer::INDEXING_FORMAT_PRODUCT_AND_MODEL_INDEX,
                $context
            ) : [];

        $familyVariantCode = null;
        if ($product->isVariant()) {
            $familyVariant = $product->getFamilyVariant();
            $familyVariantCode = null !== $familyVariant ? $familyVariant->getCode() : null;
        }
        $data[self::FIELD_FAMILY_VARIANT] = $familyVariantCode;

        $parentCode = null;
        if ($product->isVariant() && null !== $product->getParent()) {
            $parentCode = $product->getParent()->getCode();
        }
        $data[self::FIELD_PARENT] = $parentCode;

        $data[StandardPropertiesNormalizer::FIELD_VALUES] = !$product->getValues()->isEmpty()
            ? $this->serializer->normalize(
                $product->getValues(),
                ProductModelNormalizer::INDEXING_FORMAT_PRODUCT_AND_MODEL_INDEX,
                $context
            ) : [];

        $data[self::FIELD_ANCESTORS] = $this->getAncestors($product);

        $data[StandardPropertiesNormalizer::FIELD_LABEL] = $this->getLabel(
            $data[StandardPropertiesNormalizer::FIELD_VALUES],
            $product
        );

        return $data;
    }

    /**
     * Get label of the given product
     *
     * @param array            $values
     * @param ProductInterface $product
     *
     * @return array
     */
    private function getLabel(array $values, ProductInterface $product): array
    {
        $family = $product->getFamily();
        if (null === $family || null === $family->getAttributeAsLabel()) {
            return [];
        }

        $valuePath = sprintf('%s-text', $family->getAttributeAsLabel()->getCode());
        if (!isset($values[$valuePath])) {
            return [];
        }

        return $values[$valuePath];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof ProductInterface
            && ProductModelNormalizer::INDEXING_FORMAT_PRODUCT_AND_MODEL_INDEX === $format;
    }

    /**
     * Normalizes all the values of a product model and its parents.
     *
     * @param null|ProductModelInterface $productModel
     * @param array                 $context
     *
     * @return mixed
     */
    private function getAllParentsValues($productModel, array $context) : array
    {
        if (null === $productModel || $productModel->getValues()->isEmpty()) {
            return [];
        }

        $productModelNormalizedValues = $this->serializer->normalize(
            $productModel->getValues(),
            ProductModelNormalizer::INDEXING_FORMAT_PRODUCT_AND_MODEL_INDEX,
            $context
        );

        return array_merge(
            $productModelNormalizedValues,
            $this->getAllParentsValues($productModel->getParent(), $context)
        );
    }

    /**
     * @param ProductInterface $product
     *
     * @return array
     */
    private function getAncestors(ProductInterface $product): array
    {
        $ancestorsIds = [];
        $ancestorsCodes = [];
        $ancestorsLabels = [];
        if ($product->isVariant()) {
            $ancestorsIds = $this->getAncestorsIds($product);
            $ancestorsCodes = $this->getAncestorsCodes($product);
            $ancestorsLabels = $this->getAncestorsLabels($product);
        }

        $ancestors = [
            'ids' => $ancestorsIds,
            'codes' => $ancestorsCodes,
            'labels' => $ancestorsLabels,
        ];

        return $ancestors;
    }

    /**
     * @param EntityWithFamilyVariantInterface $entityWithFamilyVariant
     *
     * @return array
     */
    private function getAncestorsIds(EntityWithFamilyVariantInterface $entityWithFamilyVariant): array
    {
        $ancestorsIds = [];
        while (null !== $parent = $entityWithFamilyVariant->getParent()) {
            $ancestorsIds[] = 'product_model_' . $parent->getId();
            $entityWithFamilyVariant = $parent;
        }

        return $ancestorsIds;
    }

    /**
     * @param EntityWithFamilyVariantInterface $entityWithFamilyVariant
     *
     * @return array
     */
    private function getAncestorsCodes(EntityWithFamilyVariantInterface $entityWithFamilyVariant)
    {
        $ancestorsCodes = [];
        while (null !== $parent = $entityWithFamilyVariant->getParent()) {
            $ancestorsCodes[] = $parent->getCode();
            $entityWithFamilyVariant = $parent;
        }

        return $ancestorsCodes;
    }

    /**
     * @todo merge master: remove "$hasRepositories".
     *
     * Retrieves ancestors labels for each locales and channels.
     *
     * @param EntityWithFamilyVariantInterface $entity
     *
     * @return array
     */
    private function getAncestorsLabels(EntityWithFamilyVariantInterface $entity): array
    {
        $family = $entity->getFamily();
        if (null === $family) {
            return [];
        }

        $attributeAsLabel = $family->getAttributeAsLabel();
        if (null === $attributeAsLabel) {
            return [];
        }

        $hasRepositories = false;
        if (null !== $this->channelRepository && null !== $this->localeRepository) {
            $hasRepositories = true;
        }

        $ancestorsLabels = [];
        $attributeCodeAsLabel = $attributeAsLabel->getCode();
        switch (true) {
            case $attributeAsLabel->isScopable() && $attributeAsLabel->isLocalizable() && $hasRepositories:
                $ancestorsLabels = $this->getLocalizableAndScopableLabels($entity, $attributeCodeAsLabel);
                break;

            case $attributeAsLabel->isScopable() && $hasRepositories:
                $ancestorsLabels = $this->getScopableLabels($entity, $attributeCodeAsLabel);
                break;

            case $attributeAsLabel->isLocalizable() && $hasRepositories:
                $ancestorsLabels = $this->getLocalizableLabels($entity, $attributeCodeAsLabel);
                break;

            default:
                $value = $entity->getValue($attributeCodeAsLabel);
                if (null !== $value) {
                    $ancestorsLabels['<all_channels>']['<all_locales>'] = $value->getData();
                }
                break;
        }

        return $ancestorsLabels;
    }

    /**
     * @param EntityWithFamilyVariantInterface $entity
     * @param string $attributeCodeAsLabel
     *
     * @return array
     */
    private function getLocalizableAndScopableLabels(
        EntityWithFamilyVariantInterface $entity,
        string $attributeCodeAsLabel
    ): array {
        $ancestorsLabels = [];
        $localeCodes = $this->localeRepository->getActivatedLocaleCodes();
        foreach ($this->channelRepository->getChannelCodes() as $channelCode) {
            foreach ($localeCodes as $localeCode) {
                $value = $entity->getValue($attributeCodeAsLabel, $localeCode, $channelCode);
                if (null !== $value) {
                    $ancestorsLabels[$channelCode][$localeCode] = $value->getData();
                }
            }
        }

        return $ancestorsLabels;
    }

    /**
     * @param EntityWithFamilyVariantInterface $entity
     * @param string $attributeCodeAsLabel
     *
     * @return array
     */
    private function getScopableLabels(EntityWithFamilyVariantInterface $entity, string $attributeCodeAsLabel): array
    {
        $ancestorsLabels = [];
        foreach ($this->channelRepository->getChannelCodes() as $channelCode) {
            $value = $entity->getValue($attributeCodeAsLabel, null, $channelCode);
            if (null !== $value) {
                $ancestorsLabels[$channelCode]['<all_locales>'] = $value->getData();
            }
        }

        return $ancestorsLabels;
    }

    /**
     * @param EntityWithFamilyVariantInterface $entity
     * @param string $attributeCodeAsLabel
     *
     * @return array
     */
    private function getLocalizableLabels(EntityWithFamilyVariantInterface $entity, string $attributeCodeAsLabel): array
    {
        $ancestorsLabels = [];
        $localeCodes = $this->localeRepository->getActivatedLocaleCodes();
        foreach ($localeCodes as $localeCode) {
            $value = $entity->getValue($attributeCodeAsLabel, $localeCode);
            if (null !== $value) {
                $ancestorsLabels['<all_channels>'][$localeCode] = $value->getData();
            }
        }

        return $ancestorsLabels;
    }
}
