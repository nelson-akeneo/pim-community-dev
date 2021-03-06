<?php

namespace Pim\Component\Catalog\Value;

use Pim\Component\Catalog\Model\AbstractValue;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\AttributeOptionInterface;
use Pim\Component\Catalog\Model\ValueInterface;

/**
 * Product value for "pim_catalog_simpleselect" attribute type
 *
 * @author    Marie Bochu <marie.bochu@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class OptionValue extends AbstractValue implements OptionValueInterface
{
    /** @var AttributeOptionInterface|null */
    protected $data;

    /**
     * @param AttributeInterface            $attribute
     * @param string                        $channel
     * @param string                        $locale
     * @param AttributeOptionInterface|null $data
     */
    public function __construct(
        AttributeInterface $attribute,
        $channel,
        $locale,
        AttributeOptionInterface $data = null
    ) {
        $this->setAttribute($attribute);
        $this->setScope($channel);
        $this->setLocale($locale);

        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        if (null !== $option = $this->getData()) {
            $optionValue = $option->getOptionValue();

            if (null === $optionValue || null === $optionValue->getValue()) {
                return '['.$option->getCode().']';
            }

            return (string) $optionValue->getValue();
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function isEqual(ValueInterface $value)
    {
        if (!$value instanceof OptionValueInterface ||
            $this->getScope() !== $value->getScope() ||
            $this->getLocale() !== $value->getLocale()) {
            return false;
        }

        $comparedOption = $value->getData();
        $thisOption = $this->getData();

        if (null === $thisOption && null === $comparedOption) {
            return true;
        }
        if (null === $thisOption || null === $comparedOption) {
            return false;
        }

        return $comparedOption->getCode() === $thisOption->getCode() &&
            $comparedOption->getLocale() === $thisOption->getLocale();
    }
}
