<?php
/**
 * @author Alexey Baranov <alexey.baranov@outlook.com>
 * @date: 18.03.2017 16:27
 */

namespace Teamwox;

/**
 * Class Formatter
 *
 * @package Teamwox
 */
class Formatter
{
    /**
     * Formats message
     *
     * @param string $content
     * @return string
     */
    public function format($content)
    {
        $content = $this->makePerfectTables($content);

        return $content;
    }

    /**
     * Applies TeamWox styles and attrs to tables
     *
     * @param string $content
     * @return string
     */
    private function makePerfectTables($content)
    {
        return preg_replace("/(<table[^>]*>)/", '<table class="standart" cellspacing="0" cellpadding="3" width="100%">', $content);
    }
}
