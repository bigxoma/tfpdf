<?php


namespace tFPDF;

class GraphicReportPDF extends PDF implements GraphicReportPDFInterface
{
    const DEFAULT_FONT = 'Roboto';
    const COLOR_BLACK = [0,0,0];
    const PAGE_HEIGHT = 270;
    private $tableLabels;
    private $tableOptions;
    private $pngPath = NULL;
    private $NbVal;
    private $legends;
    private $wLegend;
    private $sum;

    public function __construct($pageOrientation = 'P', $pageUnits = 'mm', $pageSize = 'A4')
    {
        parent::__construct($pageOrientation, $pageUnits, $pageSize);

        $this->AddFont(self::DEFAULT_FONT,'','roboto.ttf',true);
        $this->AddFont(self::DEFAULT_FONT,'B','robotobd.ttf',true);
        $this->AddFont(self::DEFAULT_FONT,'BI','robotobi.ttf',true);
        $this->AddFont(self::DEFAULT_FONT,'I','robotoi.ttf',true);
    }

    /**
     * Sets path for icons directory
     * @param string $pngPath
     */
    public function setPNGPath(string $pngPath) : void {
        $this->pngPath = $pngPath;
    }

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
    public function renderTable(iterable $list, array $labels = [], array $tableOptions = []) : void {
        $this->setTableOptions($tableOptions);
        $this->setTableLabels($labels);
        $borderColor = isset($this->tableOptions['borderColor']) ? $this->tableOptions['borderColor'] : self::COLOR_BLACK;;
        $this->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);

        $this->renderTableContent($list);
    }

    /**
     * Determines whether or not page break is needed.
     * Calculates max cell height in one row taking into account cell width.
     * If max row height is more than empty page space - page break is needed.
     * @param $rowContent
     * @return bool
     */
    public function isBreakPage(array $rowContent) : bool {
        $estimatedMaxStringHeight = array_reduce($rowContent, function ($carry, $cell) {
            $width = isset($cell['options']) && isset($cell['options']['width']) ? $cell['options']['width'] : 20;
            return max($carry, strlen($cell['content']) * 2 / $width);
        }, 0);
        $estimatedMaxRowHeight = array_reduce($rowContent, function ($carry, $cell) {
            $height = isset($cell['options']) && isset($cell['options']['height']) ? $cell['options']['height'] : 12;
            return max($carry, $height);
        }, 0);

        return ($estimatedMaxStringHeight * $estimatedMaxRowHeight) > (self::PAGE_HEIGHT - $this->GetY());
    }

    /**
     * Draws bar chart
     * @param int $w chart width
     * @param int $count
     * @param array $data chart data
     * @param array $colors
     * @param int $maxVal max value in chart
     * @param bool $convertBarVals does convert value (duration) to human format time
     * @param string $barName bar name
     */
    public function drawBarChart(int $w, int $count, array $data, array $colors, int $maxVal, bool $convertBarVals, string $barName) : void {
        $h = 297 - $this->GetY();
        if ($h > $count * 12) {
            $this->drawBarName($barName);
            $this->barDiagram($w, $count * 12 + 2, $data, $colors, $maxVal, $convertBarVals);

            // blank space after graph
            $this->Ln(20);
        } else {
            $this->AddPage();
            $this->drawBarName($barName);
            $newH = 297 - $this->GetY();
            if ($newH > $count * 12) {
                $this->barDiagram($w, $count * 12 + 1, $data, $colors, $maxVal, $convertBarVals);
            } else {
                $this->barDiagram($w, ($newH / $count), $data, $colors, $maxVal, $convertBarVals);
            }
        }
    }

    /**
     * Draws vertical bar chart
     * @param array $chartData
     * @param array $legendList
     */
    public function drawVerticalBarCharts(array $chartData, array $legendList) : void {
        $this->SetFont( GraphicReportPDF::DEFAULT_FONT, '', 10);
        list($red, $green, $blue) = self::COLOR_BLACK;
        $this->SetTextColor($red, $green, $blue);
        $XPage = $this->GetX();
        $YPage = $this->GetY();
        $chartHeight = 120;
        $chartWidth = 184;
        $chartPadding = 2;
        $XStart = $XPage + 5;
        $YStart = $YPage + 13;

        // Legend
        $this->drawVerticalBarLegend($XStart, $YPage, $legendList);
        // Grid
        $this->drawVerticalBarGrid($XStart, $YStart, $chartWidth, $chartHeight);

        // Draw vertical bar
        $chartXStart = $XStart + $chartPadding;
        $chartYStart = $YStart + $chartHeight;
        $labelHeight = 7;
        $barLineWidth = count($chartData) > 15 ? 2 : 4;
        $seriesInterval = (($chartWidth - 2 * $chartPadding) - $barLineWidth * 3 * count($chartData)) / (count($chartData) + 1);
        $this->drawVerticalBars($chartData, $chartXStart, $chartYStart, $chartHeight, $seriesInterval, $barLineWidth, $labelHeight, $legendList);

        $this->SetXY($XPage, $YStart + $chartHeight + $labelHeight);
    }

    /**
     * Draws legend
     * @param $xStart
     * @param $yStart
     * @param $legendList [['color' => [0, 0, 0], 'text' => 'example'], ... ]
     */
    private function drawVerticalBarLegend($xStart, $yStart, $legendList) {
        foreach ($legendList as $i => $legend) {
            $this->drawLegendItem($i === 0 ? $xStart : $this->GetX(), $yStart, 4, $legend['color'], $legend['text']);
        }
    }

    /**
     * Draws legend item
     * @param $x
     * @param $y
     * @param $hLegend
     * @param $colors
     * @param $text
     */
    private function drawLegendItem($x, $y, $hLegend, $colors, $text) {
        list($red, $green, $blue) = $colors;
        $this->SetFillColor($red, $green, $blue);
        $this->Rect($x, $y, $hLegend, $hLegend, 'F');
        $this->SetX($x + $hLegend + 2);
        $this->Cell(35, $hLegend, $text);
    }

    /**
     * Draws grid
     * @param $XStart
     * @param $YStart
     * @param $chartWidth
     * @param $chartHeight
     */
    private function drawVerticalBarGrid($XStart, $YStart, $chartWidth, $chartHeight) {
        $gridStep = $chartHeight / 4;
        $gridLabels = ['100', '75', '50', '25', '0'];
        list($red, $green, $blue) = self::COLOR_BLACK;
        $this->SetFillColor($red, $green, $blue);
        $this->SetDrawColor($red, $green, $blue);
        for ($i = 0; $i < 5; $i++) {
            $this->Line($XStart, $YStart + $gridStep * $i, $XStart + $chartWidth, $YStart + $gridStep * $i);
            $this->Text($XStart - strlen($gridLabels[$i]) * 2.3, $YStart + $gridStep * $i, $gridLabels[$i]);
        }
    }

    /**
     * Draws vertical bars
     * @param array $chartData with week names and week value
     * @param $chartXStart
     * @param $chartYStart
     * @param $chartHeight
     * @param int $seriesInterval distance between series
     * @param int $barLineWidth width of one bar column
     * @param int $labelHeight height of labels for week names
     * @param array $legendList [['color' => [0, 0, 0], 'text' => 'example'], ... ]
     */
    private function drawVerticalBars($chartData, $chartXStart, $chartYStart, $chartHeight,
                                               $seriesInterval, $barLineWidth, $labelHeight, $legendList) {
        $i = 1;
        foreach ($chartData as $name => $series) {
            $seriesXStart = $chartXStart + $seriesInterval * $i + $barLineWidth * 3 * ($i - 1);
            // Draw week name
            $this->SetFont( GraphicReportPDF::DEFAULT_FONT, '', $barLineWidth > 2 ? 10 : 8);
            $this->SetXY($seriesXStart, $chartYStart);
            $this->Cell($barLineWidth * 3, $labelHeight, $name, 0, 0, 'C');
            // Draw bar series
            $this->SetXY($seriesXStart, $chartYStart);
            foreach ($legendList as $c => $legend) {
                list($red, $green, $blue) = $legend['color'];
                $this->SetFillColor($red, $green, $blue);

                $columnHeight = $series[$c] / 100 * $chartHeight;
                $this->Rect($this->GetX(), $chartYStart - $columnHeight, $barLineWidth, $columnHeight, 'F');
                // Labels for column
                $this->SetXY($this->GetX(), $chartYStart - $columnHeight - 3);
                $this->SetFont( GraphicReportPDF::DEFAULT_FONT, '', $barLineWidth > 2 ? 8 : 4);
                $this->Cell($barLineWidth > 2 ? 4 : 2, 3, $series[$c], 0, 2 , 'C');
                $this->SetXY($this->GetX() + $barLineWidth, $chartYStart);
            }
            $i++;
        }
    }

    /**
     * Draws doughnut with colored parts
     * @param $width
     * @param $height
     * @param $data
     * @param $colors
     */
    public function doughnut($width, $height, $data, $colors) : void {
        $this->PieChart($width, $height, $data, $colors, true);
    }

    /**
     * Sets table labels
     * @param array $tableLabels
     */
    private function setTableLabels(array $tableLabels) : void {
        $this->tableLabels = $tableLabels;
    }

    /**
     * Sets table options
     * @param array $tableOptions
     */
    private function setTableOptions(array $tableOptions) : void {
        $this->tableOptions = $tableOptions;
    }

    /**
     * Draws table labels
     */
    private function renderTableLabel() : void {
        $headerTopFillColor = isset($this->tableOptions['headerTopFillColor']) ? $this->tableOptions['headerTopFillColor'] : self::COLOR_BLACK;;
        if (!empty($this->tableLabels)) {
            // Create table header
            $this->SetFont(self::DEFAULT_FONT, '', 11);
            $this->SetTextColor(144, 144, 144);
            $this->SetFillColor($headerTopFillColor[0], $headerTopFillColor[1], $headerTopFillColor[2]);
            $maxHeight = 0;
            $pushRight = 0;
            foreach ($this->tableLabels as $label) {
                if (isset($label['content'])) {
                    $labelOptions = $label['options'] ?? [];
                    $width = (int) $labelOptions['width'] ?? 45;
                    $height = (int) $labelOptions['height'] ?? 12;
                    $border = (int) $labelOptions['border'] ?? 2;
                    $lineNumber = (int) $labelOptions['lineNumber'] ?? 0;
                    $alignment = (string) $labelOptions['alignment'] ?? 'C';
                    $fill = (bool) $labelOptions['fill'] ?? false;
                    $pushRight += $width;
                    if (isset($labelOptions['isMultiCell']) && (bool)$labelOptions['isMultiCell']) {
                        $preY = $this->GetY();
                        $this->setXY($this->getX(), $this->getY() + 2);
                        $this->MultiCell($width, 6, $label['content'], $border, $alignment, $fill);
                        $maxHeight = max($maxHeight, $this->GetY() - $preY);
                        $this->SetXY($this->GetX() + $pushRight, $preY);
                    } else {
                        $this->Cell($width, $height, $label['content'], $border, $lineNumber, $alignment, $fill);
                        $maxHeight = max($maxHeight, $height);
                    }
                    $maxHeight = max($maxHeight, $height);
                }

            }
            $this->SetDrawColor(144, 144, 144);
            $this->SetLineWidth(0.2);
            $this->Line(11, $this->GetY() + $maxHeight, 200, $this->GetY() + $maxHeight);
            $this->Ln($maxHeight);
            $this->SetTextColor(144, 144, 144);
        }
    }

    /**
     * Draws table content
     * @param iterable $list
     */
    private function renderTableContent(iterable $list) : void {
        $this->renderTableLabel();
        $this->SetFillColor(243, 251, 251);
        $this->SetFont(self::DEFAULT_FONT, '', 10);
        list($red, $green, $blue) = self::COLOR_BLACK;
        $this->setTextColor($red, $green, $blue);
        if (!empty($list)) {
            foreach ($list as $row) {
                $this->renderRow($row);
            }
        }
    }

    /**
     * Draws row
     * @param array $row
     */
    private function renderRow(array $row) : void {
        $rowContent = isset($row['content']) ? $row['content'] : [];
        $rowOptions = isset($row['options']) ? $row['options'] : [];
        if (!empty($rowContent)) {
            $pushRight = 0;
            $maxHeight = 0;
            // Tries to calculate max height of row and sets page brake if it needs
            if ($this->isBreakPage($rowContent)) {
                $this->AddPage();
                $this->renderTableLabel();
                $this->SetFillColor(243, 251, 251);
                $this->SetFont(self::DEFAULT_FONT, '', 10);
                list($red, $green, $blue) = self::COLOR_BLACK;
                $this->setTextColor($red, $green, $blue);
            }

            foreach ($rowContent as $cell) {
                list ($pushRight, $maxHeight) = $this->renderCell($cell, $pushRight, $maxHeight);
            }

            if (isset($rowOptions['hasLine']) && $rowOptions['hasLine']) {
                $this->SetLineWidth(0.5);
            } else if (isset($rowOptions['hasLine']) && !$rowOptions['hasLine']) {
                $this->SetLineWidth(0.1);
            }
            $this->setDrawColor(144,144,144);
            if (empty($rowOptions['noLine'])) {
                $this->Line(11, $this->GetY() + $maxHeight,
                    200, $this->GetY() + $maxHeight);
            }
            $this->Ln($maxHeight);
        }
    }

    /**
     * Draws cell/multi-cell, calculates coordinates for next multi-cell and max height of row
     * @param array $cell
     * @param int $pushRight
     * @param int $maxHeight
     * @return array [$pushRight, $maxHeight]
     */
    private function renderCell(array $cell, int $pushRight, int $maxHeight) : array {
        $cellOptions = isset($cell['options']) ? $cell['options']: [];
        if (isset($cell['content'])) {
            $width = isset($cellOptions['width']) ? (int)$cellOptions['width'] : 45;
            $height = isset($cellOptions['height']) ? (int)$cellOptions['height'] : 10;
            $border = isset($cellOptions['border']) ? (int)$cellOptions['border'] : 2;
            $alignment = isset($cellOptions['alignment']) ? $cellOptions['alignment'] : 'L';
            $fill = isset($cellOptions['fill']) ? $cellOptions['fill'] : false;
            $lineNumber = isset($cellOptions['lineNumber']) ? (int)$cellOptions['lineNumber'] : 0;
            if (isset($cellOptions['textColor'])) {
                $this->setTextColor($cellOptions['textColor'][0],
                    $cellOptions['textColor'][1], $cellOptions['textColor'][2]);
            } else {
                list($red, $green, $blue) = self::COLOR_BLACK;
                $this->setTextColor($red, $green, $blue);
            }
            $pushRight += $width;
            if (!empty($cellOptions['icons'])) {
                $start = $this->getX();
                $this->setXY($start, $this->getY() + 2);
                $iconsInRow = 0;
                $iconWidth = 6;
                if (is_null($this->pngPath)) {
                    throw new RuntimeException('PNG path is not defined');
                }
                foreach ($cellOptions['icons'] as $i => $iconName) {
                    $this->Image($this->pngPath . $iconName . '.png');
                    $isEnoughSpace = ($width - $iconsInRow * $iconWidth) > 24;
                    $setYForLastIcon = $i + 1 !== count($cellOptions['icons']) ? 5.3 : 7.3;
                    $x = $isEnoughSpace ? $this->getX() + $iconWidth : $start;
                    $y = $this->getY() - ($isEnoughSpace ? $setYForLastIcon : 0);
                    $iconsInRow = $isEnoughSpace ? $iconsInRow + 1 : 0;
                    $this->setXY($x, $y);
                }
            }
            if (isset($cellOptions['isMultiCell']) && (bool)$cellOptions['isMultiCell']) {
                $preY = $this->GetY();
                $this->setXY($this->getX(), $this->getY() + 2);
                $this->MultiCell($width, 6, $cell['content'], $border, $alignment, $fill);
                $maxHeight = max($maxHeight, $this->GetY() - $preY) + 2;
                $this->SetXY($this->GetX() + $pushRight, $preY);
            } else {
                $this->Cell($width, $height, $cell['content'], $border, $lineNumber, $alignment, $fill);
                $maxHeight = max($maxHeight, $height);
            }
        }

        return [$pushRight, $maxHeight];
    }

    /**
     * Draws bar name
     * @param string $barName
     */
    private function drawBarName(string $barName) : void {
        $this->SetFont(self::DEFAULT_FONT, '', 10);
        $this->Cell(200, 12, $barName, 0, 0, 'C');
        $this->Ln(8);
    }

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
    public function barDiagram($w, $h, $barChartData, $colors, $maxVal, $toTime = false, $nbDiv = 4) : void {
        // sort bars by location
        ksort($barChartData);

        list($maxGrid, $margin, $YDiag, $hDiag, $XDiag, $pxDecade, $unit) = $this->calculateDataForBarDiagram($h, $barChartData, $maxVal, $nbDiv);

        $this->SetLineWidth(0.2);

        $this->SetFont(self::DEFAULT_FONT, '', 10);
        $i = 0;

        $barLineNames = [];
        $maxBars = 0;

        foreach ($barChartData as $item) {
            foreach (array_keys($item) as $loc) {
                array_push($barLineNames, $loc);
            }

            if ($maxBars === 0 || $maxBars < count($item)) {
                $maxBars = count($item);
            }
        }

        $barLineNames = array_unique($barLineNames);

        // sort legend by name
        sort($barLineNames);

        $this->barChartDrawScales($XDiag, $YDiag, $hDiag, $margin, $maxGrid, $pxDecade);

        // Render Y text
        $this->renderTextByY($barChartData, $colors, $toTime, $barLineNames, $hDiag, $YDiag, $i, $maxBars, $XDiag, $pxDecade, $maxGrid, $unit, $margin);

        $x = $this->GetX();
        $y = $this->GetY();
        $yLegend = 0;

        // Legend
        $this->drawBarLegend($barLineNames, $colors, $XDiag - 5, $w, $YDiag, $yLegend);

        // Slide down by legend or graph, what is bigger
        $this->SetXY($x, max($y, $this->GetY()));
    }

    /**
     * Draw bar lines
     *
     * @param array $barLineNames
     * @param array $barBlock
     * @param array $colors
     * @param int $XDiag x position
     * @param int $formula y position
     * @param int $unit
     * @param int $barLineHeight
     * @param bool $toTime
     */
    private function drawBarLines($barLineNames, $barBlock, $colors, $XDiag, $formula, $unit, $barLineHeight, $toTime, $maxGrid, $pxDecade) {
        foreach ($barLineNames as $call) {
            if ($call == key($barBlock)) {
                foreach ($barBlock as $key => $loc) {
                    $lineColor = $colors[strtolower($key)] ?? self::COLOR_BLACK;;
                    $this->SetFillColor($lineColor[0], $lineColor[1], $lineColor[2]);

                    $length = 0;

                    for($i = 1;  $i <= $maxGrid; $i++) {
                        $preTop = ($i === 1) ? 0 : pow(10, $i - 1);
                        $top = pow(10, $i);

                        $valPx = $pxDecade / ($top - $preTop);
                        $currentVals = min($top, $loc) - $preTop;
                        if ($currentVals < 0) {
                            break;
                        }
                        $length += $currentVals * $valPx;
                    }

                    // Render bar line (x, y, width, height, style)
                    $this->Rect($XDiag, $formula, (int)$length, $barLineHeight, 'F');
                    $this->SetFontSize(5);
                    $this->SetXY($XDiag + (int)$length, $formula + $barLineHeight / 3);
                    $toTime ? $this->Write(1, $this->convertToTime($loc)) : $this->Write(1, $loc);
                    $formula += $barLineHeight;
                }
            }
        }
    }

    /**
     * @param int $loc
     * @return false|string date in mm:ss or hh:mm:ss format
     */
    private function convertToTime($loc) {
        if ($loc < 3600) {
            return date('i:s', $loc);
        } else {
            $H = floor($loc / 3600);
            $i = ($loc / 60) % 60;
            $s = $loc % 60;
            return sprintf("%02d:%02d:%02d", $H, $i, $s);
        }
    }

    /**
     * Computes the intersection of arrays
     * @param array $arr1
     * @param array $arr2
     * @return array
     */
    private function intersectArrays($arr1, $arr2) {
        $sorted_array = [];

        foreach ($arr1 as $key => $value) {
            foreach ($arr2 as $k => $v) {
                if ($value === $k) {
                    $sorted_array[$k] = $v;
                }
            }
        }

        return $sorted_array;
    }

    /**
     * Sets legend data
     * @param array $data
     */
    private function setLegends($data) {
        $this->legends = [];
        $this->wLegend = 0;

        foreach (array_keys($data) as $key) {
            $this->legends[] = $key;
            $this->wLegend = max($this->GetStringWidth($key) + 1, $this->wLegend);
        }

        $this->sum = array_sum($data);
        $this->NbVal = count(array_unique($this->legends));
    }

    /**
     * Renders X scale with text
     * @param int $XDiag x position
     * @param int $YDiag y position
     * @param int $hDiag
     * @param int $margin
     * @param int $maxGrid max grid steps
     * @param int $stepValue quantity pixels in one grid step
     */
    private function barChartDrawScales($XDiag, $YDiag, $hDiag, $margin, $maxGrid, $stepValue) {
        list($red, $green, $blue) = self::COLOR_BLACK;
        $this->SetLineWidth(0.1);
        for ($i = 0; $i <= $maxGrid; $i++ ) {
            if ($i === 0) {
                $this->setDrawColor($red, $green, $blue);
            } else {
                $this->setDrawColor(200,200,200);
            }
            $xpos = $XDiag + $stepValue * $i;
            $this->Line($xpos, $YDiag - (($i === 0) ? 5 : 0), $xpos, $YDiag + $hDiag);
            $val = $i === 0 ? 0 : pow(10, $i);
            $ypos = $YDiag + $hDiag - $margin + 5;
            $this->Text($xpos, $ypos, $val);
        }
        $this->SetLineWidth(0.2);
        $this->setDrawColor($red, $green, $blue);
        $this->Line($XDiag, $YDiag + $hDiag - $margin + 2, $XDiag + $stepValue * $maxGrid + 5, $YDiag + $hDiag - $margin + 2);
    }

    /**
     * Draw bar legend
     *
     * @param array $barLineNames
     * @param array $colors
     * @param int $XDiag x position
     * @param int $w width
     * @param int $YDiag y position
     * @param int $yLegend
     */
    private function drawBarLegend($barLineNames, $colors, $XDiag, $w, $YDiag, $yLegend) {
        foreach ($barLineNames as $name) {
            list ($red, $green, $blue) = $colors[strtolower($name)] ?? self::COLOR_BLACK;
            $this->SetFillColor($red, $green, $blue);
            $this->setDrawColor($red, $green, $blue);
            $this->SetXY($XDiag + $w - 9, $YDiag - 4 + $yLegend);
            $this->Rect($XDiag + $w - 13, $YDiag + $yLegend, 3, 3, 'DF');
            $this->Write(11, $name);
            $yLegend += 8;
        }
    }

    /**
     * Draws pie or doughnut chart
     * @param $w
     * @param $h
     * @param $data
     * @param null $colors
     * @param bool $isDoughnut
     */
    private function PieChart($w, $h, $data, $colors = null, $isDoughnut = false) {
        $this->SetFont(self::DEFAULT_FONT, '', 10);
        $this->setLegends($data);

        $XPage = $this->GetX();
        $YPage = $this->GetY();
        $margin = 2;
        $hLegend = 5;
        $radius = min($w - $margin * 4 - $hLegend - $this->wLegend, $h - $margin * 2);
        $radius = floor($radius / 2);
        $XDiag = $XPage + $margin + $radius;
        $YDiag = $YPage + $margin + $radius;
        if ($colors == null) {
            for ($i = 0; $i < $this->NbVal; $i++) {
                $gray = $i * intval(255 / $this->NbVal);
                $colors[$i] = [$gray, $gray, $gray];
            }
        }

        //Sectors
        $this->SetLineWidth(0.2);
        $angleStart = 0;
        $angleEnd = 0;
        $i = 0;
        foreach ($data as $val) {
            $sum = doubleval($this->sum);
            if($sum != 0) {
                $angle = ($val * 360) / doubleval($this->sum);
                if ($angle != 0) {
                    $angleEnd = $angleStart + $angle;
                    $this->SetFillColor($colors[$i][0], $colors[$i][1], $colors[$i][2]);
                    $this->sector($XDiag, $YDiag, $radius, $angleStart, $angleEnd, $isDoughnut ? 'F' : 'FD');
                    $angleStart += $angle;
                }
                $i++;
            }
        }

        if ($isDoughnut) {
            $this->SetFillColor(255,255,255);
            $this->sector($XDiag, $YDiag, $radius * 0.6, 0, 360, 'F');
        }

        //Legends
        $this->SetFont(self::DEFAULT_FONT, '', 10);
        $x1 = $XPage + 2 * $radius + 4 * $margin;
        $x2 = $x1 + $hLegend + $margin;
        $y1 = $YDiag - $radius + (2 * $radius - $this->NbVal * ($hLegend + $margin)) / 2;
        for ($i = 0; $i < $this->NbVal; $i++) {
            $this->SetFillColor($colors[$i][0], $colors[$i][1], $colors[$i][2]);
            $this->SetDrawColor($colors[$i][0], $colors[$i][1], $colors[$i][2]);
            $this->Rect($x1, $y1, $hLegend, $hLegend, 'DF');
            $this->SetXY($x2, $y1);
            $this->Cell(0, $hLegend, $this->legends[$i]);
            $y1 += $hLegend + $margin;
        }
    }

    /*** START PDF_Sector ***/

    /**
     * Adds data into current page or buffer
     * @param $strData
     */
    private function _out($strData)
    {
        // Add a line to the document
        if ($this->int_state == self::DOCUMENT_STATE_CREATING) {
            $this->arr_pages[$this->int_page] .= $strData . "\n";
        } else {
            $this->str_buffer .= $strData . "\n";
        }
    }

    /**
     * Draws sector
     * @param $xc
     * @param $yc
     * @param $r
     * @param $a
     * @param $b
     * @param string $style
     * @param bool $cw
     * @param int $o
     */
    private function sector($xc, $yc, $r, $a, $b, $style = 'FD', $cw = true, $o = 90) {
        list($d, $b, $a, $k, $hp, $MyArc) = $this->calculateParams($r, $a, $b, $cw, $o);
        //first put the center
        $this->_out(sprintf('%.2F %.2F m', ($xc) * $k, ($hp - $yc) * $k));
        //put the first point
        $this->_out(sprintf('%.2F %.2F l', ($xc + $r * cos($a)) * $k, (($hp - ($yc - $r * sin($a))) * $k)));
        //draw the arc
        if ($d < M_PI / 2) {
            $this->arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
                $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
                $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
                $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
                $xc + $r * cos($b),
                $yc - $r * sin($b)
            );
        } else {
            $this->drawArc($xc, $yc, $r, $a, $b, $d);
        }
        //terminate drawing
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' || $style == 'DF') {
            $op = 'b';
        } else {
            $op = 's';
        }
        $this->_out($op);
    }

    /**
     * Draws arc
     * @param $x1
     * @param $y1
     * @param $x2
     * @param $y2
     * @param $x3
     * @param $y3
     */
    private function arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->flt_current_height;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->flt_scale_factor,
            ($h - $y1) * $this->flt_scale_factor,
            $x2 * $this->flt_scale_factor,
            ($h - $y2) * $this->flt_scale_factor,
            $x3 * $this->flt_scale_factor,
            ($h - $y3) * $this->flt_scale_factor));
    }

    /**
     * @param $xc
     * @param $yc
     * @param $r
     * @param $a
     * @param $b
     * @param $d
     */
    private function drawArc($xc, $yc, $r, $a, $b, $d): void
    {
        $b = $a + $d / 4;
        $MyArc = 4 / 3 * (1 - cos($d / 8)) / sin($d / 8) * $r;
        $this->arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
            $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
            $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
            $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
            $xc + $r * cos($b),
            $yc - $r * sin($b)
        );
        $a = $b;
        $b = $a + $d / 4;
        $this->arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
            $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
            $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
            $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
            $xc + $r * cos($b),
            $yc - $r * sin($b)
        );
        $a = $b;
        $b = $a + $d / 4;
        $this->arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
            $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
            $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
            $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
            $xc + $r * cos($b),
            $yc - $r * sin($b)
        );
        $a = $b;
        $b = $a + $d / 4;
        $this->arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
            $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
            $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
            $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
            $xc + $r * cos($b),
            $yc - $r * sin($b)
        );
    }

    /**
     * Calculates params for sector
     * @param $r
     * @param $a
     * @param $b
     * @param $cw
     * @param $o
     * @return array
     */
    private function calculateParams($r, $a, $b, $cw, $o): array
    {
        $d0 = $a - $b;
        if ($cw) {
            $d = $b;
            $b = $o - $a;
            $a = $o - $d;
        } else {
            $b += $o;
            $a += $o;
        }
        while ($a < 0) {
            $a += 360;
        }
        while ($a > 360) {
            $a -= 360;
        }
        while ($b < 0) {
            $b += 360;
        }
        while ($b > 360) {
            $b -= 360;
        }
        if ($a > $b) {
            $b += 360;
        }
        $b = $b / 360 * 2 * M_PI;
        $a = $a / 360 * 2 * M_PI;
        $d = $b - $a;
        if ($d == 0 && $d0 != 0) {
            $d = 2 * M_PI;
        }
        $k = $this->flt_scale_factor;
        $hp = $this->flt_current_height;
        if (sin($d / 2)) {
            $MyArc = 4 / 3 * (1 - cos($d / 2)) / sin($d / 2) * $r;
        } else {
            $MyArc = 0;
        }
        return [$d, $b, $a, $k, $hp, $MyArc];
    }

    /**
     * Renders text by Y
     * @param $barChartData
     * @param $colors
     * @param $toTime
     * @param $barLineNames
     * @param $hDiag
     * @param $YDiag
     * @param $i
     * @param $maxBars
     * @param $XDiag
     * @param $pxDecade
     * @param $maxGrid
     * @param $unit
     * @param $margin
     */
    private function renderTextByY($barChartData, $colors, $toTime, $barLineNames, $hDiag, $YDiag, $i, $maxBars, $XDiag, $pxDecade, $maxGrid, $unit, $margin): void
    {
        foreach ($barChartData as $item) {
            $item = $this->intersectArrays($barLineNames, $item);

            //Bar
            $cellStart = ($hDiag / ($this->NbVal) / 2);
            $yval = $YDiag + ($i + 1) * ($hDiag / ($this->NbVal)) - $cellStart;

            if (count($barChartData) > 1) {
                $barLineHeight = 11 / $maxBars;
                $formula = $yval - $cellStart + (13 / 2 - (((11 / $maxBars) * $maxBars) / 2));
            } else {
                $barLineHeight = 13 / $maxBars;
                $formula = $yval - $cellStart;
            }

            $this->setDrawColor(200, 200, 200);
            $this->Line($XDiag, $formula - 0.5, $XDiag + $pxDecade * $maxGrid, $formula - 0.5);
            list($red, $green, $blue) = self::COLOR_BLACK;
            $this->setDrawColor($red, $green, $blue);

            $this->drawBarLines($barLineNames, $item, $colors, $XDiag, $formula, $unit, $barLineHeight, $toTime, $maxGrid, $pxDecade);

            $this->SetFontSize(8);
            $this->SetXY(0, $yval - 1);
            $this->MultiCell($XDiag - $margin - 1, 3, $this->legends[$i], 0, 0, false, 2);
            $i++;
        }
    }

    /**
     * Calculates data for bar diagram
     * @param $h
     * @param $barChartData
     * @param $maxVal
     * @param $nbDiv
     * @return array
     */
    private function calculateDataForBarDiagram($h, $barChartData, $maxVal, $nbDiv): array
    {
        $maxGrid = ceil(log10($maxVal));
        // If max val is 1, then log10 returns 0, but minimum can be 1 decade
        if ($maxGrid == 0) {
            $maxGrid = 1;
        }
        $this->SetFont(self::DEFAULT_FONT, '', 10);
        list($red, $green, $blue) = self::COLOR_BLACK;
        $this->setDrawColor($red, $green, $blue);
        $this->setLegends($barChartData);

        $YPage = $this->GetY();
        $margin = 2;
        $YDiag = $YPage + $margin;
        $hDiag = floor($h);
        $XDiag = 40;
        $lDiag = 116;
        $valIndRepere = ceil($maxVal / $nbDiv);
        $maxVal = $valIndRepere * $nbDiv;
        $lRepere = floor($lDiag / $nbDiv);
        $pxDecade = floor($lDiag / $maxGrid);
        $lDiag = $lRepere * $nbDiv;
        $unit = $lDiag / $maxVal;
        $hBar = $hDiag / $this->NbVal;
        $hDiag = $hBar * $this->NbVal;
        return [$maxGrid, $margin, $YDiag, $hDiag, $XDiag, $pxDecade, $unit];
    }
    /*** END PDF_Sector ***/
}