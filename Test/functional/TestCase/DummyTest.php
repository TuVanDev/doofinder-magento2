<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Doofinder\Feed\Test\TestCase;

use Magento\Mtf\Page\FrontendPage;
use Magento\Mtf\TestCase\Injectable;

/**
 * Dummy test, checks cms index works
 */
class DummyTest extends Injectable
{
    /**
     * @var FrontendPage
     */
    private $page;

    /**
     * Setup necessary data for test
     *
     * @param FrontendPage $page
     * @return void
     * @phpcs:disable PHPCompatibility.PHP.ReservedFunctionNames.MethodDoubleUnderscore
     */
    public function __inject(
        FrontendPage $page
    ) {
        // phpcs:enable
        $this->page = $page;
    }

    /**
     * Perform test
     *
     * @return void
     */
    public function test()
    {
        $this->page->open();
    }
}
