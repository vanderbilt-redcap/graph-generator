<?php

$pids = [73708 => 174292, 73747 => 174379, 73940 => 174778];
$thresholdTs = strtotime("2023-02-26");

$pdfModule = \ExternalModules\ExternalModules::getModuleInstance("vanderbilt_pdf_modify");
foreach ($pids as $project_id => $event_id) {
    $redcapData = \REDCap::getData($project_id, "json-array", NULL, ["studentid", "terranovatestdate"]);
    foreach ($redcapData as $row) {
        if ($row['terranovatestdate']) {
            $ts = strtotime($row['terranovatestdate']);
            if ($ts > $thresholdTs) {
                $record = $row["studentid"];
                $instrument = "terranova_2018_and_later";
                $instance = 1;
                $module->hook_save_record($project_id, $record, $instrument, $event_id, NULL, NULL,NULL, $instance);
                $pdfModule->hook_save_record($project_id, $record, $instrument, $event_id, NULL, NULL,NULL, $instance);
            }
        }
    }
}
