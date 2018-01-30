<?php
namespace Vanderbilt\GraphGeneratorExternalModule;
require_once ('jpgraph/jpgraph.php');
require_once ('jpgraph/jpgraph_bar.php');
require_once ('jpgraph/jpgraph_line.php');

class GraphGeneratorExternalModule extends \ExternalModules\AbstractExternalModule{

    public function __construct(){
        parent::__construct();
    }

	function hook_save_record($project_id, $record, $instrument, $event_id){

        $survey_form = $this->getProjectSetting("survey-form",$project_id);

        //If we are in the correct instrument
        if ($survey_form && in_array($instrument,$survey_form)) {
            $graph_title = $this->getProjectSetting("graph-title", $project_id);
            $graph_parameters = $this->getProjectSetting("graph-parameters", $project_id);

            $graph_parameters = preg_split("/[;,]+/", $graph_parameters);


            $data = \REDCap::getData($project_id, 'array', $record);
            $all_data_array = array();
            $all_data = true;
            foreach ($graph_parameters as $param) {
                $var_name = str_replace('[', '', trim($param));
                $var_name = str_replace(']', '', $var_name);

                if ($data[$record][$event_id][$var_name] != "") {
                    array_push($all_data_array, $data[$record][$event_id][$var_name]);
                } else {
                    $all_data = false;
                    break;
                }
            }

            if ($all_data) {
                $this->generate_graph($project_id, $record, $event_id, $graph_title, $all_data_array);
            }
        }
	}

    function generate_graph($project_id,$record,$event_id,$graph_title,$all_data_array){
        $graph_text = $this->getProjectSetting("graph-text",$project_id);
        $graph_color = $this->getProjectSetting("graph-color",$project_id);
        $graph_background = $this->getProjectSetting("graph-background",$project_id);
        $graph_right_label = $this->getProjectSetting("graph-right-label",$project_id);
        $graph_left_label = $this->getProjectSetting("graph-left-label",$project_id);
        $graph_band = $this->getProjectSetting("graph-band",$project_id);
        $graph_size = $this->getProjectSetting("graph-size",$project_id);

        $graph_text = preg_split("/[;,]+/", $graph_text);
        $graph_color = preg_split("/[;,]+/", $graph_color);
        $graph_size = preg_split("/[;,]+/", $graph_size);

        $graph_yaxis_min = $this->getProjectSetting("graph-yaxis-min",$project_id);
        $graph_yaxis_max = $this->getProjectSetting("graph-yaxis-max",$project_id);
        $graph_yaxis_increments = $this->getProjectSetting("graph-yaxis-increments",$project_id);

        $count = 0;
        $positions_array = array();
        for ($position = $graph_yaxis_min; $position <=$graph_yaxis_max; $position+=$graph_yaxis_increments){
            array_push($positions_array,$position);
        }
        if(!in_array ($graph_yaxis_max,$positions_array)){
            array_push($positions_array,$graph_yaxis_max);
        }

        $max_data = max($all_data_array);
        if($max_data <= 100){
            $scale = $this->scaleTicks($max_data);
        }else{
            $scale = "";
        }

        // Create the graph.
        $w = ($graph_size[0] == "")? 750:$graph_size[0];
        $h = ($graph_size[1] == "")? 750:$graph_size[1];
        $graph = new \Graph($w,$h);

        // Slightly bigger margins than default to make room for titles
        $graph->SetMargin(50,60,30,30);

        //To set the image background transparent
        $graph->SetMarginColor('White:0.6');
        $graph->SetFrame(true,'White:0.6',1);
        $graph->SetBox(false);
        if($graph_background == "trans"){
            $graph->img->SetTransparent("white");
        }

        // Setup the scales for X,Y and Y2 axis
        $graph->SetScale("textlin"); // X and Y axis
        if($graph_right_label != "") {
            $graph->SetY2Scale("lin"); // Y2 axis
        }
        $graph->SetShadow();

        //Main title
        $graph->title->Set($graph_title);
        $graph->title->SetFont(FF_ARIAL,FS_BOLD,16);

        // Create the bar plots
        $bplot = new \BarPlot($all_data_array);
        // Create the grouped bar plot
        $gbplot = new \GroupBarPlot(array($bplot));


        // Title for X-axis
        //        $graph->xaxis->title->Set('Measurement');
//        $graph->xaxis->title->SetMargin(5);
//        $graph->xaxis->title->SetFont(FF_ARIAL,FS_NORMAL,11);
        $graph->xaxis->SetTickLabels($graph_text);
        $graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,15);

        $bplot->SetColor('black');
        $graph->graph_theme = null;

        // Title for Y-axis
        $graph->yaxis->title->Set($graph_left_label);
        $graph->yaxis->title->SetMargin(5);
        $graph->yaxis->title->SetFont(FF_ARIAL,FS_NORMAL,15);
        $graph->yaxis->SetTickPositions($positions_array);


        //scale the ticks to show them all
        $graph->yaxis->scale->SetGrace($scale);

        //Add right side titles
        if($graph_right_label != ""){
            // Create Y2 scale data set
            $graph->y2axis->title->Set($graph_right_label);
            $graph->y2axis->title->SetFont(FF_ARIAL,FS_NORMAL,15);
            $graph->y2axis->SetColor('#d8ecf3@1.0:1.3');
            //We scale this axis to hide the extra bars
            $graph->y2axis->scale->SetGrace($scale);
        }

        //Add it to the graph
        $graph->Add($gbplot);
        $graph->AddY2($gbplot);

        if($graph_band != ""){
            $graph_band = preg_split("/[;,]+/", $graph_band);
            //Add band
            $graph->ygrid->Show(false);
            $band = new \PlotBand(HORIZONTAL,BAND_SOLID,$graph_band[0],$graph_band[1],'#d8ecf3');
            $band->ShowFrame(false);
            $graph->Add($band);

            //Add lines
            $hband = new \PlotBand(HORIZONTAL,BAND_HLINE,0,100,'lightgray');
            $hband->ShowFrame(false);
            $hband->SetDensity(27);
            $graph->Add($hband);
        }

        //Bar colors
        $bplot->SetFillColor($graph_color);

        // Setup the values that are displayed on top of each bar
        $bplot->value->Show();
        $bplot->value->SetFormat('%d');
        $bplot->value->SetFont(FF_ARIAL,FS_BOLD,16);
        $bplot->value->SetColor("black");
        // Center the values in the bar
        //$bplot->SetValuePos('center');

        //SAVE IMAGE TO DB
//        $graph->img->SetImgFormat($graph_format);


        $img = $graph->Stroke(_IMG_HANDLER);
        ob_start();

        imagepng($img);
        $img_data = ob_get_contents();
        ob_end_clean();

        echo '<img src="data:image/png;base64,';
        echo base64_encode($img_data);
        echo '"/>';
        die;

        //Save image to DB
        $this->saveToFieldName($project_id, $record, $event_id, $img_data,"png");
    }

    function scaleTicks($max_data){
        $scale_ticks = array(0=>9000,1=>9000,2=>4500,3=>3000,4=>2200,5=>1800,6=>1500,7=>1300,8=>1100,9=>1100,10=>900,
                            11=>800,12=>700,13=>600,14=>600,15=>550,16=>500,17=>450,18=>450,19=>400,20=>400,
                            21=>350,22=>350,23=>300,24=>300,25=>300,26=>250,27=>250,28=>250,29=>240,30=>220,
                            31=>220,32=>200,33=>200,34=>190,35=>180,36=>170,37=>170,38=>160,39=>150,40=>150,
                            41=>140,42=>130,43=>130,44=>120,45=>120,46=>110,47=>110,48=>100,49=>100,50=>100,
                            51=>90,52=>90,53=>80,54=>80,55=>80,56=>70,57=>70,58=>70,59=>60,60=>60,
                            61=>60,62=>60,63=>50,64=>50,65=>50,66=>50,67=>40,68=>40,69=>40,70=>40,
                            71=>40,72=>30,73=>30,74=>30,75=>30,76=>30,77=>20,78=>20,79=>20,80=>20,
                            81=>20,82=>20,83=>20,84=>10,85=>10,86=>10,87=>10,88=>10,89=>10,90=>10,
                            91=>0,92=>0,93=>0,94=>0,95=>0,96=>0,97=>0,98=>0,99=>0,100=>0
        );

        return $scale_ticks[$max_data];

    }
    function saveToFieldName($project_id, $record, $event_id, $img_data, $graph_format){

        $fileFieldName = $this->getProjectSetting("graph-saveto",$project_id);
        if ($fileFieldName) {
            $fileFieldName = str_replace('[', '', trim($fileFieldName));
            $fileFieldName = str_replace(']', '', $fileFieldName);

            /***SAVE GRAPH IMAGE***/
            $fileName = "graph_image";
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