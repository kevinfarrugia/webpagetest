<?php

$cruxStyles = false;
// Helper to get the CrUX data for a given URL
function GetCruxDataForURL($url, $mobile = false)
{
    $crux_data = null;
    $api_key = GetSetting('crux_api_key', null);
    if (isset($api_key) && strlen($api_key) && strlen($url)) {
        if (substr($url, 0, 4) != 'http') {
            $url = 'http://' . $url;
        }

        $cache_key = sha1($url);
        if ($mobile) {
            $cache_key .= '.mobile';
        }
        $crux_data = GetCachedCruxData($cache_key);

        if (!isset($crux_data)) {
            $options = array(
                'url' => $url,
                'formFactor' => $mobile ? 'PHONE' : 'DESKTOP'
            );
            $result = http_post_raw(
                "https://chromeuxreport.googleapis.com/v1/records:queryRecord?key=$api_key",
                json_encode($options),
                'application/json',
                true
            );
            if (isset($result) && is_string($result)) {
                $crux_data = $result;
            }

            CacheCruxData($cache_key, $crux_data);
        }
    }
    return $crux_data;
}

function GetCachedCruxData($cache_key)
{
    $crux_data = null;
    $today = gmdate('Ymd');
    $cache_path = __DIR__ . "/../results/crux_cache/$today/" . substr($cache_key, 0, 2) . "/$cache_key.json";
    if (file_exists($cache_path)) {
        $crux_data = file_get_contents($cache_path);
    }

    return $crux_data;
}

function CacheCruxData($cache_key, $crux_data)
{
    if (isset($crux_data) && strlen($crux_data)) {
        $today = gmdate('Ymd');
        $cache_path = __DIR__ . "/../results/crux_cache/$today/" . substr($cache_key, 0, 2);
        mkdir($cache_path, 0777, true);
        $cache_path .= "/$cache_key.json";
        file_put_contents($cache_path, $crux_data);
    }
}

// Delete any cache directories that don't match the current date
function PruneCruxCache()
{
    $cache_path = __DIR__ . '/../results/crux_cache';
    if (is_dir($cache_path)) {
        $today = gmdate('Ymd');
        $files = scandir($cache_path);
        foreach ($files as $file) {
            if ($file !== '.' && $file != '..' && $file != $today) {
                delTree("$cache_path/$file");
            }
        }
    }
}
// returns a string of the Real User Measurement title for results.php if CrUX has collectionPeriod
function RealUserMeasurementCruxTitle($pageData)
{
    if (
        isset($pageData['CrUX']['collectionPeriod']) &&
        isset($pageData['CrUX']['collectionPeriod']['firstDate']) &&
        isset($pageData['CrUX']['collectionPeriod']['lastDate'])
    ) {
        $firstDate = $pageData['CrUX']['collectionPeriod']['firstDate'];
        $lastDate = $pageData['CrUX']['collectionPeriod']['lastDate'];
        $startDate = date('F j\, Y', mktime(0, 0, 0, $firstDate['month'], $firstDate['day'], $firstDate['year']));
        $endDate = date('F j\, Y', mktime(0, 0, 0, $lastDate['month'], $lastDate['day'], $lastDate['year']));
        // Example: Real User Measurements (Collected anonymously by Chrome browser via Chrome User Experience Report, between October 15, 2022 and September 18, 2022)
        return sprintf('<h3 class="hed_sub">
                            Real User Measurements
                            <em>(Collected anonymously by Chrome browser via Chrome User Experience Report, between %s and %s)</em>
                        </h3>', $startDate, $endDate);
    } else {
        return '<h3 class="hed_sub">
                    Real User Measurements
                    <em>(Collected anonymously by Chrome browser via Chrome User Experience Report)</em>
                </h3>';
    }
}

function InsertCruxHTML($fvRunResults, $rvRunResults, $metric = '', $includeLabels = true, $includeMetricName = true)
{
    $pageData = null;
    $rvPageData = null;
    global $cruxStyles;
    if (isset($fvRunResults)) {
        $stepResult = $fvRunResults->getStepResult(1);
        if ($stepResult) {
            $pageData = $stepResult->getRawResults();
        }
    }
    if (isset($rvRunResults)) {
        $stepResult = $rvRunResults->getStepResult(1);
        if ($stepResult) {
            $rvPageData = $stepResult->getRawResults();
            if (!isset($pageData) && isset($rvPageData) && isset($rvPageData['CrUX'])) {
                $pageData = array('CrUX' => $rvPageData['CrUX']);
            }
        }
    }
    if (
        isset($pageData) &&
        is_array($pageData) &&
        isset($pageData['CrUX']) &&
        is_array($pageData['CrUX']) &&
        isset($pageData['CrUX']['metrics'])
    ) {
        if (!$cruxStyles) {?>
            <?php
            $cruxStyles = true;
        }
        echo '<div class="crux">';
        echo RealUserMeasurementCruxTitle($pageData);
        echo '<div class="crux_legends"><strong>Metrics from this run:</strong>';
        if (isset($pageData) && (isset($pageData['chromeUserTiming.firstContentfulPaint']) || isset($pageData['chromeUserTiming.LargestContentfulPaint']) || isset($pageData['chromeUserTiming.CumulativeLayoutShift']))) {
            echo ' &nbsp;<span class="legend"><span class="fvarrow">&#x25BC</span> ';
            if ($includeLabels) {
                echo "First View";
            }
            echo '</span>';
        }
        if (isset($rvPageData) && (isset($rvPageData['chromeUserTiming.firstContentfulPaint']) || isset($rvPageData['chromeUserTiming.LargestContentfulPaint']) || isset($rvPageData['chromeUserTiming.CumulativeLayoutShift']))) {
            echo ' &nbsp;<span class="legend"><span class="rvarrow">&#x25BC</span>';
            if ($includeLabels) {
                echo "Repeat View";
            }
            echo '</span>';
        }
        echo '</div>';


        // The individual metrics
        echo '<div class="cruxbars">';
        if ($metric == '') {
            //show all
            InsertCruxMetricHTML($pageData, $rvPageData, 'chromeUserTiming.firstContentfulPaint', 'first_contentful_paint', 'First Contentful Paint', 'FCP', $includeMetricName);
            InsertCruxMetricHTML($pageData, $rvPageData, 'chromeUserTiming.LargestContentfulPaint', 'largest_contentful_paint', 'Largest Contentful Paint', 'LCP', $includeMetricName);
            InsertCruxMetricHTML($pageData, $rvPageData, 'chromeUserTiming.CumulativeLayoutShift', 'cumulative_layout_shift', 'Cumulative Layout Shift', 'CLS', $includeMetricName);
            InsertCruxMetricHTML($pageData, $rvPageData, null, 'first_input_delay', 'First Input Delay', 'FID', $includeMetricName);
            InsertCruxMetricHTML($pageData, $rvPageData, 'TTFB', 'experimental_time_to_first_byte', 'Time to First Byte', 'TTFB', $includeMetricName);
            InsertCruxMetricHTML($pageData, $rvPageData, null, 'experimental_interaction_to_next_paint', 'Interaction to Next Paint', 'INP', $includeMetricName);
        } elseif ($metric == 'fcp') {
            InsertCruxMetricHTML($pageData, $rvPageData, 'chromeUserTiming.firstContentfulPaint', 'first_contentful_paint', 'First Contentful Paint', 'FCP', $includeMetricName);
        } elseif ($metric == 'lcp') {
            InsertCruxMetricHTML($pageData, $rvPageData, 'chromeUserTiming.LargestContentfulPaint', 'largest_contentful_paint', 'Largest Contentful Paint', 'LCP', $includeMetricName);
        } elseif ($metric == 'cls') {
            InsertCruxMetricHTML($pageData, $rvPageData, 'chromeUserTiming.CumulativeLayoutShift', 'cumulative_layout_shift', 'Cumulative Layout Shift', 'CLS', $includeMetricName);
        } elseif ($metric == 'fid') {
            InsertCruxMetricHTML($pageData, $rvPageData, null, 'first_input_delay', 'First Input Delay', 'FID', $includeMetricName);
        } elseif ($metric == 'ttfb') {
            InsertCruxMetricHTML($pageData, $rvPageData, 'TTFB', 'experimental_time_to_first_byte', 'Time to First Byte', 'TTFB', $includeMetricName);
        } elseif ($metric == 'inp') {
            InsertCruxMetricHTML($pageData, $rvPageData, null, 'experimental_interaction_to_next_paint', 'Interaction to Next Paint', 'INP', $includeMetricName);
        }

        echo '</div>';

        echo '</div>';
    }
}

function InsertCruxMetricHTML($fvPageData, $rvPageData, $metric, $crux_metric, $label, $short, $includeLabels = true)
{
    $fvValue = null;
    $rvValue = null;
    $histogram = null;
    $p75 = null;
    if (isset($fvPageData) && is_array($fvPageData) && isset($fvPageData[$metric])) {
        $fvValue = $fvPageData[$metric];
    }
    if (isset($rvPageData) && is_array($rvPageData) && isset($rvPageData[$metric])) {
        $rvValue = $rvPageData[$metric];
    }
    if (isset($fvPageData['CrUX']['metrics'][$crux_metric]['histogram'])) {
        $histogram = $fvPageData['CrUX']['metrics'][$crux_metric]['histogram'];
    }
    if (isset($fvPageData['CrUX']['metrics'][$crux_metric]['percentiles']['p75'])) {
        $p75 = $fvPageData['CrUX']['metrics'][$crux_metric]['percentiles']['p75'];
    }
    if (isset($histogram) && is_array($histogram) && count($histogram) == 3) {
        InsertCruxMetricImage($label, $short, $histogram, $p75, $fvValue, $rvValue, $includeLabels);
    }
}

function InsertCruxMetricImage($label, $short, $histogram, $p75, $fvValue, $rvValue, $includeLabels = true)
{
    // Figure out offsets and adjustments to the svg
    $width = 200;
    if (is_float($p75)) {
        $p75 = round($p75, 3);
    }
    if (is_float($fvValue)) {
        $fvValue = round($fvValue, 3);
    }
    $goodPct = intval(round($histogram[0]['density'] * 100));
    $fairPct = intval(round($histogram[1]['density'] * 100));
    $poorPct = intval(round($histogram[2]['density'] * 100));
    $poorStart = $goodPct + $fairPct;
    $poorPct = 100 - $poorStart;
    $goodText = '';
    if ($goodPct >= 7) {
        $pos = $goodPct / 2;
        $goodText = "<text x='$pos%' y='16' text-anchor='middle' font-size='12' font-family='Open Sans, sans-serif' fill='white'>$goodPct%</text>";
    }
    $fairText = '';
    if ($fairPct >= 7) {
        $pos = $goodPct + ($fairPct / 2);
        $fairText = "<text x='$pos%' y='16' text-anchor='middle' font-size='12' font-family='Open Sans, sans-serif' fill='black'>$fairPct%</text>";
    }
    $poorText = '';
    if ($poorPct >= 7) {
        $pos = $goodPct + $fairPct + ($poorPct / 2);
        $poorText = "<text x='$pos%' y='16' text-anchor='middle' font-size='12' font-family='Open Sans, sans-serif' fill='white'>$poorPct%</text>";
    }
    $p75arrow = '';
    $p75text = '';
    $maxVal = max($p75, $fvValue, $rvValue);
    $unit = $short === 'CLS' ? '' : ' ms';
    if (isset($p75)) {
        $pos = 5 + GetCruxValuePosition($p75, $maxVal, $histogram, $width, $color);
        $start = $pos - 5;
        $end = $pos + 5;
        $p75arrow = "<svg x='5' width='250' y='55' height='15'><polygon points='$start,10 $pos,0 $end,10' fill='$color'/></svg>";
        $text = "p75 ($p75$unit)";
        $anchor = $pos < 90 ? 'start' : 'end';
        $textPos = $pos < 90 ? $pos + 12 : $pos - 5;
        $p75text = "<text x='$textPos' y='67' font-size='12' text-anchor='$anchor' font-family='Open Sans, sans-serif'>$text</text>";
    }
    $fvArrow = '';
    if (isset($fvValue)) {
        $pos = 5 + GetCruxValuePosition($fvValue, $maxVal, $histogram, $width, $color);
        $start = $pos - 5;
        $end = $pos + 5;
        $fvArrow = "<svg x='5' width='250' y='15' height='15'><polygon points='$start,5 $pos,15 $end,5' fill='#1072ba'><title>$fvValue$unit</title></polygon></svg>";
    }
    $rvArrow = '';
    if (isset($rvValue)) {
        $pos = 5 + GetCruxValuePosition($rvValue, $maxVal, $histogram, $width, $color);
        $start = $pos - 5;
        $end = $pos + 5;
        $rvArrow = "<svg x='5' width='250' y='15' height='15'><polygon points='$start,5 $pos,15 $end,5' fill='#5dbbe8'><title>$rvValue$unit</title></polygon></svg>";
    }
    $image_width = $width + 20;
    $svg = <<<EOD
<svg viewBox="0 0 $image_width 80" version='1.1' xmlns='http://www.w3.org/2000/svg'>
<svg x='10' width='$width' y='30' height='25'>
    <rect x='0' width='100%' height='100%' fill='#009316'><title>$goodPct%</title></rect>
    <rect x='$goodPct%' width='100%' height='100%' fill='#ffa400'><title>$fairPct%</title></rect>
    <rect x='$poorStart%' width='100%' height='100%' fill='#ff4e42'><title>$poorPct%</title></rect>
    $goodText
    $fairText
    $poorText
</svg>
$p75arrow
$rvArrow
$fvArrow
$p75text
EOD;
    if ($includeLabels) {
        $svg .= "<text x='100' y='16' font-size='13' text-anchor='middle' font-family='Open Sans, sans-serif'>$label ($short)</text>";
    }
    $svg .= '</svg>';
    echo $svg;
}

function GetCruxValuePosition($value, $maxVal, $histogram, $width, &$color)
{
    $pos = null;
    $goodWidth = $histogram[0]['density'] * floatval($width);
    $fairWidth = $histogram[1]['density'] * floatval($width);
    $poorWidth = $histogram[2]['density'] * floatval($width);
    if ($value <= $histogram[0]['end']) {
        $pct = floatval($value) / floatval($histogram[0]['end']);
        $pos = $pct * $goodWidth;
        $color = '#009316';
    } elseif ($value <= $histogram[1]['end']) {
        $pct = floatval($value - $histogram[1]['start']) / floatval($histogram[1]['end'] - $histogram[1]['start']);
        $pos = $goodWidth + ($pct * $fairWidth);
        $color = '#ffa400';
    } else {
        $pct = min(floatval($value - ($maxVal - $value)) / floatval($maxVal), 1.0);
        $pos = $goodWidth + $fairWidth + ($pct * $poorWidth);
        $color = '#ff4e42';
    }
    return intval($pos);
}