<?php

namespace tFPDF;

class PDF_Sector extends PDF
{
    protected function _out($strData)
    {
        // Add a line to the document
        if ($this->int_state == self::DOCUMENT_STATE_CREATING) {
            $this->arr_pages[$this->int_page] .= $strData . "\n";
        } else {
            $this->str_buffer .= $strData . "\n";
        }
    }

    public function writeToFile($destination='', $name='')
    {
        $this->SetLink($destination);
        file_put_contents($name, parent::output()) ;
    }

    function Sector($xc, $yc, $r, $a, $b, $style = 'FD', $cw = true, $o = 90) {
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
        //first put the center
        $this->_out(sprintf('%.2F %.2F m', ($xc) * $k, ($hp - $yc) * $k));
        //put the first point
        $this->_out(sprintf('%.2F %.2F l', ($xc + $r * cos($a)) * $k, (($hp - ($yc - $r * sin($a))) * $k)));
        //draw the arc
        if ($d < M_PI / 2) {
            $this->_Arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
                $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
                $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
                $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
                $xc + $r * cos($b),
                $yc - $r * sin($b)
            );
        } else {
            $b = $a + $d / 4;
            $MyArc = 4 / 3 * (1 - cos($d / 8)) / sin($d / 8) * $r;
            $this->_Arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
                $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
                $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
                $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
                $xc + $r * cos($b),
                $yc - $r * sin($b)
            );
            $a = $b;
            $b = $a + $d / 4;
            $this->_Arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
                $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
                $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
                $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
                $xc + $r * cos($b),
                $yc - $r * sin($b)
            );
            $a = $b;
            $b = $a + $d / 4;
            $this->_Arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
                $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
                $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
                $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
                $xc + $r * cos($b),
                $yc - $r * sin($b)
            );
            $a = $b;
            $b = $a + $d / 4;
            $this->_Arc($xc + $r * cos($a) + $MyArc * cos(M_PI / 2 + $a),
                $yc - $r * sin($a) - $MyArc * sin(M_PI / 2 + $a),
                $xc + $r * cos($b) + $MyArc * cos($b - M_PI / 2),
                $yc - $r * sin($b) - $MyArc * sin($b - M_PI / 2),
                $xc + $r * cos($b),
                $yc - $r * sin($b)
            );
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

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->flt_current_height;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->flt_scale_factor,
            ($h - $y1) * $this->flt_scale_factor,
            $x2 * $this->flt_scale_factor,
            ($h - $y2) * $this->flt_scale_factor,
            $x3 * $this->flt_scale_factor,
            ($h - $y3) * $this->flt_scale_factor));
    }
}

