<?php
namespace Vanderbilt\GraphGeneratorExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

require_once ('jpgraph/jpgraph.php');
require_once ('jpgraph/jpgraph_bar.php');
require_once ('jpgraph/jpgraph_line.php');

class GraphGeneratorExternalModule extends \ExternalModules\AbstractExternalModule{

    public function __construct(){
        parent::__construct();
    }

    function validateSettings($settings){
        $error = "";
        foreach ($settings['graph-size'] as $index => $graph_size){
            $param_vars = preg_split("/[;,]+/", $graph_size);
            if($graph_size != "") {
                if (sizeof($param_vars) != "2" && $graph_size != "") {
                    $error .= "The format of the Image size " . ($index + 1) . " is incorrect. Please enter a correct format: width,height.\n";
                }
            }
        }

        foreach ($settings['graph-saveto'] as $index => $graph_saveto){
            if($graph_saveto != "") {
                if (strpos($graph_saveto, '[') !== false || strpos($graph_saveto, ']') !== false) {
                    $error .= "The field name " . ($index + 1) . " to store the graph can't contain []. Please enter a correct format.\n";
                }
            }
        }

        foreach ($settings['graph-band'] as $index => $graph_band){
            $param_vars = preg_split("/[;,]+/", $graph_band);
            if($graph_band != "") {
                if (sizeof($param_vars) != "2") {
                    $error .= "The format of the Band position " . ($index + 1) . " is incorrect. Please enter a correct format: bottom,top.\n";
                }
            }
        }

        foreach ($settings['graph-yaxis'] as $index => $graph_yaxis){
            $param_vars = preg_split("/[;,]+/", $graph_yaxis);
            if($graph_yaxis != "") {
                if (sizeof($param_vars) != "3") {
                    $error .= "The format of the yaxis " . ($index + 1) . " is incorrect. Please enter a correct format: Min, Max, increments.\n";
                }
            }
        }

        foreach ($settings['graph-parameters'] as $index => $graph_parameters){
            if($graph_parameters != "") {
                $graph_parameters = explode("\n", $graph_parameters);
                foreach ($graph_parameters as $param) {
                    $param_vars = preg_split("/[;,]+/", $param);
                    if($param != "") {
                        if (sizeof($param_vars) != "3") {
                            $error .= "The format of the parameters " . ($index + 1) . " is incorrect. Please enter a correct format: [redcap_var],my text, #000000.\n";
                        } else if (strpos($param_vars[0], '[') === false || strpos($param_vars[0], ']') === false) {
                            $error .= "The format of the parameter variable " . $param_vars[0] . " on " . ($index + 1) . " is incorrect. Please enter a correct format: [redcap_var],my text, #000000.\n";
                        }
                    }
                }
            }
        }

        return $error;
    }

	function hook_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash,$response_id, $repeat_instance){
        $graph_data = $this->getProjectSetting("graph",$project_id);

        foreach ($graph_data as $index=>$graph) {
            $survey_form = $this->getProjectSetting("survey-form",$project_id)[$index];
            //If we are in the correct instrument
            if ($survey_form == $instrument) {
                $this->generate_graph($project_id, $record, $event_id,$index,$repeat_instance,$survey_form);
            }
        }
	}

    function generate_graph($project_id,$record,$event_id,$index,$repeat_instance,$survey_form){
        $data = \REDCap::getData($project_id, 'array', $record);
        $isRepeatInstrument = false;
        if((array_key_exists('repeat_instances',$data[$record]))){
            $isRepeatInstrument = true;
        }

        $graph_parameters = $this->getProjectSetting("graph-parameters", $project_id)[$index];
        $graph_parameters = explode("\n",$graph_parameters);

        $all_data_array = array();
        $graph_text = array();
        $graph_color = array();
        foreach ($graph_parameters as $param) {
            $param_vars = preg_split("/[;,]+/", $param);
            $var_name = str_replace('[', '', trim($param_vars[0]));
            $var_name = str_replace(']', '', $var_name);

            $value = $data[$record][$event_id][$var_name];
            if($isRepeatInstrument && $data[$record]['repeat_instances'][$event_id][$survey_form][$repeat_instance][$var_name] != ""){
                //Repeat instruments
                $value = $data[$record]['repeat_instances'][$event_id][$survey_form][$repeat_instance][$var_name];
            }else if($isRepeatInstrument && $data[$record]['repeat_instances'][$event_id][''][$repeat_instance][$var_name] != ""){
                //Repeat events
                $value = $data[$record]['repeat_instances'][$event_id][''][$repeat_instance][$var_name];
            }

            if($value != "" && is_numeric($value)){
                array_push($all_data_array, $value);
                array_push($graph_text, trim($param_vars[1]));
                array_push($graph_color, trim(strtolower($param_vars[2])));
            }
        }

        if(sizeof($all_data_array) > 0) {
            $graph_title = $this->getProjectSetting("graph-title", $project_id)[$index];
            $graph_background = $this->getProjectSetting("graph-background", $project_id)[$index];
            $graph_right_label = $this->getProjectSetting("graph-right-label", $project_id)[$index];
            $graph_left_label = $this->getProjectSetting("graph-left-label", $project_id)[$index];
            $graph_band = $this->getProjectSetting("graph-band", $project_id)[$index];
            $graph_size = $this->getProjectSetting("graph-size", $project_id)[$index];
            $font_size = ($this->getProjectSetting("font-size", $project_id)[$index] == "") ? 15 : $this->getProjectSetting("font-size", $project_id)[$index];

            $graph_size = preg_split("/[;,]+/", $graph_size);

            $graph_yaxis = $this->getProjectSetting("graph-yaxis", $project_id)[$index];
            $graph_yaxis = preg_split("/[;,]+/", $graph_yaxis);

            $graph_yaxis_min = ($graph_yaxis[0] == "") ? 0 : $graph_yaxis[0];
            $graph_yaxis_max = ($graph_yaxis[1] == "") ? 100 : $graph_yaxis[1];
            $graph_yaxis_increments = ($graph_yaxis[2] == "") ? 10 : $graph_yaxis[2];

            $count = 0;
            $positions_array = array();
            for ($position = $graph_yaxis_min; $position <= $graph_yaxis_max; $position += $graph_yaxis_increments) {
                array_push($positions_array, $position);
            }
            if (!in_array($graph_yaxis_max, $positions_array)) {
                array_push($positions_array, $graph_yaxis_max);
            }

            $max_data = max($all_data_array);
            if ($max_data <= 100) {
                $scale = $this->scaleTicks($max_data);
            } else {
                $scale = "";
            }

            try {

                /***GRAPH***/
                $w = ($graph_size[0] == "") ? 750 : $graph_size[0];
                $h = ($graph_size[1] == "") ? 750 : $graph_size[1];
                $graph = new \Graph($w, $h);

                // Slightly bigger margins than default to make room for titles
                //        $graph->SetMargin(50,60,30,30);
                $graph->SetMargin(60 + $font_size, 60, 50, 50);

                //To set the image background transparent
                $graph->SetMarginColor('White:0.6');
                $graph->SetFrame(true, 'White:0.6', 1);
                $graph->SetBox(false);
                if ($graph_background == "trans") {
                    $graph->img->SetTransparent("white");
                }

                // Setup the scales for X,Y and Y2 axis
                $graph->SetScale("textlin"); // X and Y axis
                if ($graph_right_label != "") {
                    $graph->SetY2Scale("lin"); // Y2 axis
                }
                $graph->SetShadow();

                //Main title
                $graph->title->Set($graph_title);
                $graph->title->SetFont(FF_ARIAL, FS_BOLD, $font_size + 1);

                // Create the bar plots
                $bplot = new \BarPlot($all_data_array);
                // Create the grouped bar plot
                $gbplot = new \GroupBarPlot(array($bplot));

                // Title for X-axis
                $graph->xaxis->SetTickLabels($graph_text);
                $graph->xaxis->SetFont(FF_ARIAL, FS_NORMAL, $font_size);

                $bplot->SetColor('black');
                $graph->graph_theme = null;

                // Title for Y-axis
                $graph->yaxis->title->Set($graph_left_label);
                $graph->yaxis->title->SetMargin($font_size + 10);
                $graph->yaxis->title->SetFont(FF_ARIAL, FS_NORMAL, $font_size);
                $graph->yaxis->SetTickPositions($positions_array);
                $graph->yaxis->SetFont(FF_ARIAL, FS_BOLD, $font_size);
                //        $graph->yaxis->SetFont(FF_FONT2,FS_BOLD);
                $graph->yaxis->HideLine(false);
                $graph->yaxis->HideTicks(false, false);

                //scale the ticks to show them all
                $graph->yaxis->scale->SetGrace($scale);

                //Add right side titles
                if ($graph_right_label != "") {
                    // Create Y2 scale data set
                    $graph->y2axis->title->Set($graph_right_label);
                    $graph->y2axis->title->SetMargin(10);
                    $graph->y2axis->title->SetFont(FF_ARIAL, FS_NORMAL, $font_size);
                    $graph->y2axis->SetColor('#d8ecf3@1.0:1.3');
                    $graph->y2axis->HideTicks();
                    //We scale this axis to hide the extra bars
                    $graph->y2axis->scale->SetGrace($scale);

                }

                //Add it to the graph
                $graph->Add($gbplot);
                $graph->AddY2($gbplot);

                if ($graph_band != "") {
                    $graph_band = preg_split("/[;,]+/", $graph_band);
                    //Add band
                    $graph->ygrid->Show(false);
                    $band = new \PlotBand(HORIZONTAL, BAND_SOLID, $graph_band[0], $graph_band[1], '#d8ecf3');
                    $band->ShowFrame(false);
                    $graph->Add($band);
                }

                //Bar colors
                $bplot->SetFillColor($graph_color);

                // Setup the values that are displayed on top of each bar
                $bplot->value->Show();
                $bplot->value->SetFormat('%d');
                $bplot->value->SetFont(FF_ARIAL, FS_BOLD, $font_size + 1);
                $bplot->value->SetColor("black");
                // Center the values in the bar
                //$bplot->SetValuePos('center');

                //SAVE IMAGE TO DB
                //$graph->img->SetImgFormat($graph_format);

//                try{
                    $img = $graph->Stroke(_IMG_HANDLER);
//                }catch(\JpGraphExceptionL $e) {
//                    echo "fffffff";
//                    die;
//                }

                ob_start();

                imagepng($img);
                $img_data = ob_get_contents();
                ob_end_clean();

                //        echo '<img src="data:image/png;base64,';
                //        echo base64_encode($img_data);
                //        echo '"/>';
                //        die;


                //Save image to DB
                $this->saveToFieldName($project_id, $record, $event_id, $img_data, "png", $index, $repeat_instance);

            } catch (\JpGraphExceptionL $e) {
                $this->sendEmailError($project_id,$e);
            }
            catch (\JpGraphException $e) {
                $this->sendEmailError($project_id,$e);
            }
            catch (Exception $e) {
                $this->sendEmailError($project_id,$e);
            }
        }
    }

    function sendEmailError($project_id,$e){
        $email_error = $this->getProjectSetting("error", $project_id);

        $body = "<p>There was an error in producing the GRAPH image: ".$e->getMessage()."</p>";
        $subject = \REDCap::getProjectTitle() . ": GRAPH submission (error)";

        ExternalModules::sendErrorEmail($email_error,$subject,$body);
    }

    function scaleTicks($max_data){
        $scale_ticks = array(0=>9500,1=>9500,2=>4800,3=>3100,4=>2400,5=>1900,6=>1500,7=>1300,8=>1100,9=>1000,10=>900,
                            11=>800,12=>700,13=>650,14=>600,15=>550,16=>500,17=>480,18=>450,19=>420,20=>400,
                            21=>360,22=>350,23=>330,24=>300,25=>300,26=>280,27=>270,28=>250,29=>240,30=>220,
                            31=>220,32=>200,33=>200,34=>190,35=>180,36=>170,37=>170,38=>160,39=>150,40=>150,
                            41=>140,42=>130,43=>130,44=>120,45=>120,46=>110,47=>110,48=>100,49=>100,50=>100,
                            51=>90,52=>90,53=>80,54=>80,55=>80,56=>70,57=>70,58=>70,59=>65,60=>65,
                            61=>60,62=>60,63=>55,64=>55,65=>50,66=>50,67=>45,68=>45,69=>40,70=>40,
                            71=>40,72=>30,73=>30,74=>30,75=>30,76=>30,77=>25,78=>25,79=>25,80=>25,
                            81=>20,82=>20,83=>20,84=>14,85=>14,86=>14,87=>14,88=>10,89=>10,90=>10,
                            91=>5,92=>5,93=>5,94=>5,95=>5,96=>0,97=>0,98=>0,99=>0,100=>0
        );

        return $scale_ticks[$max_data];

    }
    function saveToFieldName($project_id, $record, $event_id, $img_data, $graph_format,$index,$repeat_instance){
        $fileFieldName = $this->getProjectSetting("graph-saveto",$project_id)[$index];
        if ($fileFieldName) {
            $fileFieldName = str_replace('[', '', trim($fileFieldName));
            $fileFieldName = str_replace(']', '', $fileFieldName);

            /***SAVE GRAPH IMAGE***/
            $fileName = "graph_image_".$project_id."_".$record."_".$fileFieldName."_".$repeat_instance;
            $reportHash = $fileName;
            $storedName = md5($reportHash);
            $filePath = EDOC_PATH.$storedName;

            $filesize = file_put_contents(EDOC_PATH.$storedName, $img_data);

            $sql = "INSERT INTO redcap_edocs_metadata (stored_name,mime_type,doc_name,doc_size,file_extension,gzipped,project_id,stored_date) VALUES
                  ('$storedName','application/octet-stream','$reportHash.".$graph_format."',".$filesize.",'.".$graph_format."',0,'".$project_id."','".date('Y-m-d h:i:s')."')";
                    db_query($sql);
            $edoc = db_insert_id();

            if ($edoc) {
                $instance = db_real_escape_string($_GET['instance']);
                $quotedInstance = "'".$instance."'";
                $instanceComparison = "instance = $quotedInstance";
                if ($instance == 1) {
                    $quotedInstance = "NULL";
                    $instanceComparison = "instance IS NULL";
                }
                $sql1 = "SELECT value
							FROM redcap_data
							WHERE (project_id = $project_id)
								AND (event_id = '".$event_id."')
								AND (record = '".db_real_escape_string($record)."')
								AND (instance = '".db_real_escape_string($instance)."')
								AND (field_name = '".db_real_escape_string($fileFieldName)."');";
                $result1 = db_query($sql1);
                if (db_num_rows($result1) === 0) {
                    $sql2 = "INSERT INTO redcap_data (
										`project_id`,
										`event_id`,
										`record`,
										`field_name`,
										`value`,
										`instance` )
									VALUES (
										$project_id,
										'$event_id',
										'".db_real_escape_string($record)."',
										'".db_real_escape_string($fileFieldName)."',
										'".db_real_escape_string($edoc)."',
										$quotedInstance
									);";
                    db_query($sql2);
                } else if (db_num_rows($result1) == 1) {
                    $sql2 = "UPDATE redcap_data
								SET value = '".db_real_escape_string($edoc)."'
								WHERE (project_id = $project_id)
									AND (event_id = '".$event_id."')
									AND (record = '".db_real_escape_string($record)."')
									AND (field_name = '".db_real_escape_string($fileFieldName)."')
									AND (".$instanceComparison.");";
                    db_query($sql2);
                } else {
                    # Should never happen
                    throw new Exception(db_num_rows($result1)." rows returned in query $sql1.");
                }
            }
        }
    }
}
?>