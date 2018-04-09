<?php


namespace tFPDF;


interface GraphicReportPDFInterface
{
    /**
     * Sets path for icons directory
     * @param string $pngPath
     */
    public function setPNGPath(string $pngPath) : void;

    /**
     * Rendering table
     * yield [
     *      [  // Row example
     *          "content" => [
     *              [ // Cell example
     *                  "content" => "Text",
     *                  "options" => [ "width" => 22, "height" => 10, "border" => 0, "alignment" => "C", "fill" => false, "lineNumber" => 0, "isMultiCell" => true ]
     *              ],
     *              ... [cell], [cell], [cell],
     *           ],
     *          "options" => [ "hasLine" => true ]
     *      ],
     *      ... [row], [row], [row],
     * ];
     *
     * $labels = [
     *      [   // Label example
     *          "content" => "System",
     *          "options" => [ "width" => 22, "height" => 10, "border" => 0, "alignment" => "C", "fill" => false, "lineNumber" => 0 ]
     *      ],
     *      ... [label], [label], [label],
     * ];
     *
     * $tableOptions = [
     *      "headerTopFillColour" => [125, 152, 179],
     *      "borderColor" => [50, 50, 50]
     * ]
     * This params can be easy extended
     * @param iterable $list
     * @param array $labels
     * @param array $tableOptions
     */
    public function renderTable(iterable $list, array $labels = [], array $tableOptions = []) : void;

    /**
     * Checks if new row fits on the page or it needs to create a new page
     * @param array $rowContent
     * @return bool
     */
    public function isBreakPage(array $rowContent) : bool;

    /**
     * Draws bar chart
     * @param int $w chart width
     * @param int $count
     * @param array $data report data
     * @param array $colors
     * @param int $maxVal max value in chart
     * @param bool $convertBarVals does convert value (duration) to human format time
     * @param string $barName report name
     */
    public function drawBarChart(int $w, int $count, array $data, array $colors, int $maxVal, bool $convertBarVals, string $barName) : void;

    /**
     * Draws doughnut with colored parts
     * @param $width
     * @param $height
     * @param $data
     * @param $colors
     */
    public function doughnut($width, $height, $data, $colors) : void;

    /**
     * Draws bar diagram
     * @param int $w chart width
     * @param int $h chart height
     * @param array $barChartData report data
     * @param array $colors
     * @param int $maxVal in chart
     * @param bool $toTime
     * @param int $nbDiv
     */
    public function barDiagram($w, $h, $barChartData, $colors, $maxVal, $toTime, $nbDiv) : void;

    /**
     * Draws vertical bar chart
     * @param array $chartData
     * @param array $legendList
     */
    public function drawVerticalBarCharts(array $chartData, array $legendList) : void;
}