<?php

namespace OxygenElements;

use function Breakdance\Elements\c;

class Sample extends \Breakdance\Elements\Element
{
    static function name()
    {
        return 'Sample';
    }

    static function className()
    {
        return 'oxy-sample';
    }

    static function category()
    {
        return 'basic';
    }

    static function availableIn()
    {
        return ['oxygen'];
    }

    static function contentControls()
    {
        return [c('content', 'Content', [c('text', 'Text')])];
    }

    static function spacingBars()
    {
        return [
            [
                'cssProperty' => 'margin-top',
                'affectedPropertyPath' => 'design.spacing.margin_top.%%BREAKPOINT%%',
            ],
        ];
    }

    static function propertyPathsToWhitelistInFlatProps()
    {
        return ['content.content.text'];
    }

    static function propertyPathsToSsrElementWhenValueChanges()
    {
        return ['content.content.text'];
    }
}
