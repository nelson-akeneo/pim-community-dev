<?php

declare(strict_types=1);

namespace Pim\Bundle\CatalogBundle\tests\integration\Doctrine\ORM\Query;

use Akeneo\Test\Integration\TestCase;

class GetChannelActiveLocaleCodesIntegration extends TestCase
{
    public function testItGetsAllActiveLocalesForAChannel()
    {
        $localeCodes = $this->getFromTestContainer('pim_catalog.query.get_channel_active_locale_codes')->execute('tablet');
        sort($localeCodes);
        $this->assertSame(['de_DE', 'en_US', 'fr_FR'], $localeCodes);

        $localeCodes = $this->getFromTestContainer('pim_catalog.query.get_channel_active_locale_codes')->execute('ecommerce_china');
        sort($localeCodes);
        $this->assertSame(['en_US', 'zh_CN'], $localeCodes);
    }

    protected function getConfiguration()
    {
        return $this->catalog->useTechnicalCatalog();
    }
}
