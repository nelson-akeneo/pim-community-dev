<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Channel\Query;

interface FindActivatedCurrenciesInterface
{
    /**
     * Method that returns a list of currencies codes activated for the given channel.
     *
     * @param string $channelCode
     *
     * @return array
     */
    public function forChannel(string $channelCode): array;

    /**
     * Method that returns a list of all currencies codes activated.
     *
     * @param string $channelCode
     *
     * @return array
     */
    public function forAllChannels(): array;
}
