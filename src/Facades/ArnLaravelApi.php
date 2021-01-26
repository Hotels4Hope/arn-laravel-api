<?php

namespace Hotels4Hope\ArnLaravelApi\Facades;

use Illuminate\Support\Facades\Facade;

class ArnLaravelApi extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'arnlaravelapi';
    }
}
