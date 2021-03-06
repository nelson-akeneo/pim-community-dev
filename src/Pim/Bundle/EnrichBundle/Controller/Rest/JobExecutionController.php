<?php

namespace Pim\Bundle\EnrichBundle\Controller\Rest;

use Akeneo\Bundle\BatchQueueBundle\Manager\JobExecutionManager;
use Pim\Bundle\ConnectorBundle\EventListener\JobExecutionArchivist;
use Pim\Bundle\EnrichBundle\Doctrine\ORM\Repository\JobExecutionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author    Alban Alnot <alban.alnot@consertotech.pro>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class JobExecutionController
{
    /** @var TranslatorInterface */
    protected $translator;

    /** @var JobExecutionArchivist */
    protected $archivist;

    /** @var SerializerInterface */
    protected $serializer;

    /** @var JobExecutionManager */
    protected $jobExecutionManager;

    /** @var JobExecutionRepository */
    protected $jobExecutionRepo;

    /** @var NormalizerInterface */
    private $normalizer;

    /**
     * @param TranslatorInterface    $translator
     * @param JobExecutionArchivist  $archivist
     * @param SerializerInterface    $serializer
     * @param JobExecutionManager    $jobExecutionManager
     * @param JobExecutionRepository $jobExecutionRepo
     * @param NormalizerInterface    $normalizer
     *
     * @todo merge: Remove the serializer in master branch and keep only the normalizer.
     */
    public function __construct(
        TranslatorInterface $translator,
        JobExecutionArchivist $archivist,
        SerializerInterface $serializer,
        JobExecutionManager $jobExecutionManager,
        JobExecutionRepository $jobExecutionRepo,
        NormalizerInterface $normalizer = null
    ) {
        $this->translator = $translator;
        $this->archivist = $archivist;
        $this->serializer = $serializer;
        $this->jobExecutionManager = $jobExecutionManager;
        $this->jobExecutionRepo = $jobExecutionRepo;
        $this->normalizer = $normalizer;
    }

    /**
     * Get jobs
     *
     * @param $identifier
     *
     * @return JsonResponse
     */
    public function getAction($identifier)
    {
        $jobExecution = $this->jobExecutionRepo->find($identifier);
        if (null === $jobExecution) {
            throw new NotFoundHttpException('Akeneo\Component\Batch\Model\JobExecution entity not found');
        }

        $archives = [];
        foreach ($this->archivist->getArchives($jobExecution) as $archiveName => $files) {
            $label = $this->translator->transChoice(
                sprintf('job_tracker.download_archive.%s', $archiveName),
                count($files)
            );
            $archives[$archiveName] = [
                'label' => $label,
                'files' => $files,
            ];
        }

        $jobExecution = $this->jobExecutionManager->resolveJobExecutionStatus($jobExecution);

        $context = ['limit_warnings' => 100];

        if (null !== $this->normalizer) {
            $jobResponse = $this->normalizer->normalize($jobExecution, 'internal_api', $context);
        } else {
            $jobResponse = $this->serializer->normalize($jobExecution, 'standard', $context);
        }

        $jobResponse['meta'] = [
            'logExists'           => file_exists($jobExecution->getLogFile()),
            'archives'      => $archives,
            'id'            => $identifier
        ];

        return new JsonResponse($jobResponse);
    }
}
