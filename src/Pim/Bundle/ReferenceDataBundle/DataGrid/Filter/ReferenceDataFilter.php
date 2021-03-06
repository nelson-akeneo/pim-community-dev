<?php

namespace Pim\Bundle\ReferenceDataBundle\DataGrid\Filter;

use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Pim\Bundle\FilterBundle\Filter\ProductFilterUtility;
use Pim\Bundle\FilterBundle\Filter\ProductValue\ChoiceFilter;
use Pim\Bundle\UserBundle\Context\UserContext;
use Pim\Component\Catalog\Repository\AttributeOptionRepositoryInterface;
use Pim\Component\Catalog\Repository\AttributeRepositoryInterface;
use Pim\Component\ReferenceData\ConfigurationRegistryInterface;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Reference data filter
 *
 * @author    Marie Bochu <marie.bochu@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ReferenceDataFilter extends ChoiceFilter
{
    /** @var ConfigurationRegistryInterface */
    protected $registry;

    /**
     * TODO @merge remove optionReposClass (removed in ChoiceFilter), remove null on attributeOptionRepo
     * @param FormFactoryInterface                    $factory
     * @param ProductFilterUtility                    $util
     * @param UserContext                             $userContext
     * @param AttributeRepositoryInterface            $attributeRepository
     * @param ConfigurationRegistryInterface          $registry
     * @param AttributeOptionRepositoryInterface|null $attributeOptionRepository
     */
    public function __construct(
        FormFactoryInterface $factory,
        ProductFilterUtility $util,
        UserContext $userContext,
        AttributeRepositoryInterface $attributeRepository,
        ConfigurationRegistryInterface $registry,
        AttributeOptionRepositoryInterface $attributeOptionRepository = null
    ) {
        parent::__construct($factory, $util, $userContext, null, $attributeRepository, $attributeOptionRepository);

        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(FilterDatasourceAdapterInterface $ds, $data)
    {
        $data = $this->parseData($data);
        if (!$data) {
            return false;
        }

        $operator = $this->getOperator($data['type']);

        $this->util->applyFilter(
            $ds,
            $this->get(ProductFilterUtility::DATA_NAME_KEY),
            $operator,
            $data['value']
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormOptions()
    {
        $attribute = $this->getAttribute();
        $referenceDataName = $attribute->getReferenceDataName();
        $referenceData = $this->registry->get($referenceDataName);

        if (null === $referenceData) {
            throw new \InvalidArgumentException(sprintf('Reference data "%s" does not exist', $referenceDataName));
        }

        return array_merge(
            parent::getFormOptions(),
            [
                'choice_url_params' => [
                    'class'        => $referenceData->getClass(),
                    'dataLocale'   => $this->userContext->getCurrentLocaleCode(),
                    'collectionId' => $attribute->getId(),
                    'options'      => [
                        'type' => 'code',
                    ],
                ]
            ]
        );
    }
}
