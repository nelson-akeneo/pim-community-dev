<?php

namespace spec\Pim\Bundle\EnrichBundle\Connector\Reader\MassEdit;

use Akeneo\Component\Batch\Job\JobParameters;
use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\StorageUtils\Cursor\CursorInterface;
use Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\EnrichBundle\Connector\Reader\MassEdit\FilteredProductAndProductModelReader;
use Pim\Component\Catalog\Manager\CompletenessManager;
use Pim\Component\Catalog\Model\ChannelInterface;
use Pim\Component\Catalog\Model\ProductInterface;
use Pim\Component\Catalog\Model\ProductModelInterface;
use Pim\Component\Catalog\Query\ProductQueryBuilderFactoryInterface;
use Pim\Component\Catalog\Query\ProductQueryBuilderInterface;
use Pim\Component\Catalog\Repository\ChannelRepositoryInterface;
use Pim\Component\Catalog\Converter\MetricConverter;
use Prophecy\Argument;
use Prophecy\Promise\ReturnPromise;

class FilteredProductAndProductModelReaderSpec extends ObjectBehavior
{
    function it_is_initializable(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ChannelRepositoryInterface $channelRepository,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter
    ) {
        $this->beConstructedWith(
            $pqbFactory,
            $channelRepository,
            $completenessManager,
            $metricConverter,
            true,
            false
        );

        $this->shouldHaveType(FilteredProductAndProductModelReader::class);
    }

    function it_sets_step_execution(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ChannelRepositoryInterface $channelRepository,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        StepExecution $stepExecution
    ) {
        $this->beConstructedWith(
            $pqbFactory,
            $channelRepository,
            $completenessManager,
            $metricConverter,
            true,
            false
        );

        $this->setStepExecution($stepExecution)->shouldReturn(null);
    }

    function it_reads_products_only_and_not_product_models(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ChannelRepositoryInterface $channelRepository,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        StepExecution $stepExecution,
        ChannelInterface $channel,
        ProductQueryBuilderInterface $pqb,
        CursorInterface $cursor,
        ProductModelInterface $productModel1,
        ProductModelInterface $productModel2,
        ProductModelInterface $productModel3,
        ProductInterface $product1,
        ProductInterface $product2,
        ProductInterface $product3,
        JobParameters $jobParameters
    ) {
        $this->beConstructedWith(
            $pqbFactory,
            $channelRepository,
            $completenessManager,
            $metricConverter,
            true,
            false
        );

        $this->setStepExecution($stepExecution);

        $filters = [
            'data' => [
                [
                    'field' => 'enabled',
                    'operator' => '=',
                    'value' => true,
                ],
                [
                    'field' => 'family',
                    'operator' => 'IN',
                    'value' => [
                        'camcorder',
                    ],
                ],
            ],
            'structure' => [
                'scope' => 'mobile',
                'locales' => ['fr_FR', 'en_US'],
            ],
        ];
        $stepExecution->getJobParameters()->willReturn($jobParameters);
        $jobParameters->get('filters')->willReturn($filters);

        $channelRepository->findOneByIdentifier('mobile')->willReturn($channel);
        $channel->getCode()->willReturn('mobile');

        $pqbFactory->create(['filters' => $filters['data'], 'default_scope' => 'mobile'])
            ->shouldBeCalled()
            ->willReturn($pqb);
        $pqb->execute()
            ->shouldBeCalled()
            ->willReturn($cursor);

        $completenessManager->generateMissingForChannel($channel)->shouldBeCalled();
        $products = [$productModel1, $product1, $productModel2, $product2, $product3, $productModel3];
        $productsCount = count($products);
        $cursor->valid()->will(
            function () use (&$productsCount) {
                return $productsCount-- > 0;
            }
        );
        $cursor->current()->will(new ReturnPromise($products));
        $cursor->next()->shouldBeCalledTimes(5);

        $stepExecution->incrementSummaryInfo('read')->shouldBeCalledTimes(3);
        $metricConverter->convert(Argument::any(), $channel)->shouldBeCalledTimes(3);

        $productModel1->getCode()->willReturn('product_model_1');
        $productModel2->getCode()->willReturn('product_model_2');
        $productModel3->getCode()->willReturn('product_model_3');
        $stepExecution->addWarning('This bulk action doesn\'t support Product models entities yet.', Argument::cetera())
            ->shouldBeCalledTimes(3);

        $this->initialize();
        $this->read()->shouldReturn($product1);
        $this->read()->shouldReturn($product2);
        $this->read()->shouldReturn($product3);
        $this->read()->shouldReturn(null);
    }

    function it_reads_products_only_and_not_product_models_with_children(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ChannelRepositoryInterface $channelRepository,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        StepExecution $stepExecution,
        ChannelInterface $channel,
        ProductQueryBuilderInterface $pqb,
        CursorInterface $cursor,
        ProductModelInterface $productModel1,
        ProductModelInterface $productModel2,
        ProductModelInterface $productModel3,
        ProductInterface $product1,
        ProductInterface $product2,
        ProductInterface $product3,
        JobParameters $jobParameters
    ) {
        $this->beConstructedWith(
            $pqbFactory,
            $channelRepository,
            $completenessManager,
            $metricConverter,
            true,
            true
        );

        $this->setStepExecution($stepExecution);

        $filters = [
            'data' => [
                [
                    'field' => 'id',
                    'operator' => 'NOT IN',
                    'value' => [1, 2, 3, 4, 42],
                ],
                [
                    'field' => 'label_or_identifier',
                    'operator' => 'CONTAINS',
                    'value' => 'something',
                ],
            ],
            'structure' => [
                'scope' => 'mobile',
                'locales' => ['fr_FR', 'en_US'],
            ],
        ];
        $readChildrenFiltersData = [
            [
                'field' => 'self_and_ancestor.id',
                'operator' => 'NOT IN',
                'value' => [1, 2, 3, 4, 42],
            ],
            [
                'field' => 'self_and_ancestor.label_or_identifier',
                'operator' => 'CONTAINS',
                'value' => 'something',
            ],
        ];

        $stepExecution->getJobParameters()->willReturn($jobParameters);
        $jobParameters->get('filters')->willReturn($filters);

        $channelRepository->findOneByIdentifier('mobile')->willReturn($channel);
        $channel->getCode()->willReturn('mobile');

        $pqbFactory->create(['filters' => $readChildrenFiltersData, 'default_scope' => 'mobile'])
            ->shouldBeCalled()
            ->willReturn($pqb);
        $pqb->execute()
            ->shouldBeCalled()
            ->willReturn($cursor);

        $completenessManager->generateMissingForChannel($channel)->shouldBeCalled();
        $products = [$productModel1, $product1, $productModel2, $product2, $product3, $productModel3];
        $productsCount = count($products);
        $cursor->valid()->will(
            function () use (&$productsCount) {
                return $productsCount-- > 0;
            }
        );
        $cursor->current()->will(new ReturnPromise($products));
        $cursor->next()->shouldBeCalledTimes(5);

        $stepExecution->incrementSummaryInfo('read')->shouldBeCalledTimes(3);
        $metricConverter->convert(Argument::any(), $channel)->shouldBeCalledTimes(3);

        $productModel1->getCode()->willReturn('product_model_1');
        $productModel2->getCode()->willReturn('product_model_2');
        $productModel3->getCode()->willReturn('product_model_3');
        $stepExecution->addWarning(Argument::cetera())->shouldNotBeCalled();

        $this->initialize();
        $this->read()->shouldReturn($product1);
        $this->read()->shouldReturn($product2);
        $this->read()->shouldReturn($product3);
        $this->read()->shouldReturn(null);
    }
}
