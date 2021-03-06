<?php

namespace Pim\Component\Catalog\Validator\Constraints;

use Pim\Component\Catalog\AttributeTypes;
use Pim\Component\Catalog\EntityWithFamilyVariant\Query\GetValuesOfSiblings;
use Pim\Component\Catalog\Exception\AlreadyExistingAxisValueCombinationException;
use Pim\Component\Catalog\FamilyVariant\EntityWithFamilyVariantAttributesProvider;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\EntityWithFamilyVariantInterface;
use Pim\Component\Catalog\Model\ProductInterface;
use Pim\Component\Catalog\Model\ValueCollectionInterface;
use Pim\Component\Catalog\Repository\EntityWithFamilyVariantRepositoryInterface;
use Pim\Component\Catalog\Validator\UniqueAxesCombinationSet;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validate that an entity with family variant does not use a combination of
 * variant axis values that already exists, either in database or in an other
 * entity already processed in a batch.
 *
 * @author    Adrien Pétremann <adrien.petremann@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class UniqueVariantAxisValidator extends ConstraintValidator
{
    /** @var EntityWithFamilyVariantAttributesProvider */
    private $axesProvider;

    /** @var EntityWithFamilyVariantRepositoryInterface */
    private $entityWithFamilyVariantRepository;

    /** @var UniqueAxesCombinationSet */
    private $uniqueAxesCombinationSet;

    /** @var GetValuesOfSiblings */
    private $getValuesOfSiblings;

    // TODO merge master/4.0: remove the second argument ($repository), and make the fourth one non nullable ($getValuesOfSiblings)
    public function __construct(
        EntityWithFamilyVariantAttributesProvider $axesProvider,
        EntityWithFamilyVariantRepositoryInterface $repository,
        UniqueAxesCombinationSet $uniqueAxesCombinationSet,
        ?GetValuesOfSiblings $getValuesOfSiblings = null
    ) {
        $this->axesProvider = $axesProvider;
        $this->entityWithFamilyVariantRepository = $repository;
        $this->uniqueAxesCombinationSet = $uniqueAxesCombinationSet;
        $this->getValuesOfSiblings = $getValuesOfSiblings;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($entity, Constraint $constraint)
    {
        if (!$entity instanceof EntityWithFamilyVariantInterface) {
            throw new UnexpectedTypeException($constraint, EntityWithFamilyVariantInterface::class);
        }

        if (!$constraint instanceof UniqueVariantAxis) {
            throw new UnexpectedTypeException($constraint, UniqueVariantAxis::class);
        }

        if (null === $entity->getFamilyVariant()) {
            return;
        }

        if (null === $entity->getParent()) {
            return;
        }

        $axes = $this->axesProvider->getAxes($entity);

        if (empty($axes)) {
            return;
        }

        $this->validateValueIsNotAlreadyInDatabase($entity, $axes);
        $this->validateValueWasNotAlreadyValidated($entity, $axes);
    }

    /**
     * Adds a constraint violation if there is a sibling of "$entity" with the
     * same combination of variant axis values in database.
     *
     * @param EntityWithFamilyVariantInterface $entity
     * @param AttributeInterface[]             $axes
     */
    private function validateValueIsNotAlreadyInDatabase(EntityWithFamilyVariantInterface $entity, array $axes): void
    {
        $ownCombination = $this->getCombinationOfAxisValues($entity->getValuesForVariation(), $axes);

        if ('' === str_replace(',', '', $ownCombination)) {
            return;
        }

        // TODO merge master/4.0: remove the test, and the whole 'else' statement
        if (null !== $this->getValuesOfSiblings) {
            $siblingValues = $this->getValuesOfSiblings->for($entity);
        } else {
            $siblingValues = $this->getSiblingValues($entity);
        }

        if (empty($siblingValues)) {
            return;
        }

        $siblingsCombinations = [];
        foreach ($siblingValues as $siblingIdentifier => $values) {
            $siblingsCombinations[$siblingIdentifier] = $this->getCombinationOfAxisValues($values, $axes);
        }

        if (in_array($ownCombination, $siblingsCombinations)) {
            $alreadyInDatabaseSiblingIdentifier = array_search($ownCombination, $siblingsCombinations);

            $this->addViolation(
                $axes,
                $ownCombination,
                $entity,
                $alreadyInDatabaseSiblingIdentifier
            );
        }
    }

    /**
     * Adds a constraint violation if a sibling of "$entity" with the same
     * combination of variant axis values was already parsed.
     *
     * This means "$uniqueAxesCombinationSet" has to be stateful.
     *
     * @param EntityWithFamilyVariantInterface $entity
     * @param AttributeInterface[]             $axes
     */
    private function validateValueWasNotAlreadyValidated(EntityWithFamilyVariantInterface $entity, array $axes): void
    {
        $combination = $this->getCombinationOfAxisValues($entity->getValuesForVariation(), $axes);

        if ('' === str_replace(',', '', $combination)) {
            return;
        }

        try {
            $this->uniqueAxesCombinationSet->addCombination($entity, $combination);
        } catch (AlreadyExistingAxisValueCombinationException $e) {
            $alreadyValidatedSiblingIdentifier = $e->getEntityIdentifier();

            $this->addViolation(
                $axes,
                $combination,
                $entity,
                $alreadyValidatedSiblingIdentifier
            );
        }
    }

    /**
     * This method builds "combinations" of the given $entityWithFamilyVariant for its $axes.
     * A combination is the concatenation of all values for an axis.
     *
     * For example, the axis is made of 2 attributes: color and size.
     * Let say we have [blue] for color and [xl] for size, then the combination of this entity will be "[blue],[xl]".
     *
     * This allows use to compare multiple combinations, to look for a potential duplicate.
     *
     * @todo TIP-857: This method should be moved in "Pim\Component\Catalog\Model\EntityWithFamilyVariantInterface"
     *       and implemented in the product, published product and product model.
     *       The "$axes" should not be provided as an argument anymore, as the entity can provide them too
     *       This implies to remove "Pim\Component\Catalog\FamilyVariant\EntityWithFamilyVariantAttributesProvider"
     *       and merge its code in the product, published product and product model.
     *
     * @param EntityWithFamilyVariantInterface $entity
     * @param AttributeInterface[]             $axes
     *
     * @return string
     */
    private function getCombinationOfAxisValues(ValueCollectionInterface $values, array $axes): string
    {
        $combination = [];

        foreach ($axes as $axis) {
            $value = $values->getByCodes($axis->getCode());
            if (AttributeTypes::BOOLEAN === $axis->getType()) {
                $combination[] = (true === $value->getData() ? '1' : '0');
            } else {
                $combination[] = (string)$value;
            }
        }

        return implode(',', $combination);
    }

    /**
     * @param array                            $axes
     * @param string                           $combination
     * @param EntityWithFamilyVariantInterface $entityWithFamilyVariant
     * @param string                           $siblingIdentifier
     */
    private function addViolation(
        array $axes,
        string $combination,
        EntityWithFamilyVariantInterface $entityWithFamilyVariant,
        string $siblingIdentifier
    ): void {
        $axesCodes = implode(',', array_map(
            function (AttributeInterface $axis) {
                return $axis->getCode();
            },
            $axes
        ));

        $message = UniqueVariantAxis::DUPLICATE_VALUE_IN_PRODUCT_MODEL;
        if ($entityWithFamilyVariant instanceof ProductInterface) {
            $message = UniqueVariantAxis::DUPLICATE_VALUE_IN_VARIANT_PRODUCT;
        }

        $this->context->buildViolation($message, [
            '%values%' => $combination,
            '%attributes%' => $axesCodes,
            '%validated_entity%' => $this->getEntityIdentifier($entityWithFamilyVariant),
            '%sibling_with_same_value%' => $siblingIdentifier,
        ])->atPath('attribute')->addViolation();
    }

    /**
     * @param EntityWithFamilyVariantInterface $entity
     *
     * @return string
     */
    private function getEntityIdentifier(EntityWithFamilyVariantInterface $entity): string
    {
        if ($entity instanceof ProductInterface) {
            return $entity->getIdentifier();
        }

        return $entity->getCode();
    }

    // TODO merge master/4.0 : remove this method
    private function getSiblingValues(EntityWithFamilyVariantInterface $entity): array
    {
        $valuesIndexedByIdentifier = [];
        $siblings = $this->entityWithFamilyVariantRepository->findSiblings($entity);
        foreach ($siblings as $sibling) {
            $valuesIndexedByIdentifier[$this->getEntityIdentifier($sibling)] = $sibling->getValuesForVariation();
        }

        return $valuesIndexedByIdentifier;
    }
}
