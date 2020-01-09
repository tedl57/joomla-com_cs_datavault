<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_datavault
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2019 (c) Creative Spirits
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

jimport('libcs.dates.static');
jimport('libcs.table.showresults');

$user   = JFactory::getUser();
// check if guest user (not logged in)
if ( $user->id == 0 )
	// must be logged into to see report data
	jexit(); // quietly exit w/o output

// todo: these should be site specific plugins - kludge to get new site onlien
$reports = array();
$reports["membership_report"] = array( "name" => "Membership Report", "description" => "");
$reports["time_varying_membership_counts_report"] = array( "name" => "Time Varying Membership Counts Report", "description" => "");
$reports["website_usage_report"] = array( "name" => "Website Usage Report", "description" => "");

$action = JRequest::getVar('action','');

if ( !empty($action))
{
	if (!validAction($action,$reports))
		jexit();
	
	$action($action,$reports["$action"]);
	jexit();
}
function website_usage_report($action, $data)
{
	$db = JFactory::getDBO();
	$sql = "SELECT id,report_type,datetimestamp,report_data FROM `#__cs_report_data` WHERE `report_type` = 'webnew' ORDER BY `id` DESC LIMIT 1";
	$db->setQuery($sql);
	$dat = $db->loadAssoc();
	if ( ! isset( $dat["datetimestamp"] ) )
	{
		echo "no $action row in DB";
		jexit();
	}
	$dat = unserialize($dat["report_data"]);
	echo <<<EOT
<html>
<head>
<style type="text/css">
body {
  font-family: Arial, Verdana, "Trebuchet MS", serif;
}
</style>
</head>
<body>
EOT;
	// report title
	$ts = $dat["datetimestamp"];
	$org = userGetOrgAbbr();
	echo<<<EOT
<table>
	<tr>
		<td width="80" rowspan='2'>
			<img src='/images/site/report-logo.jpg'>
		</td>
	</tr>
	<tr>
		<td>
			<span style="font-size: 120%;font-weight: bold;">$org Web Report</span><br>
			as of $ts</h4>
		</td>
	</tr>
</table>
EOT;
	// Array ( [users] => 23 [visit_users] => 7 [days_since] => -15 [datetimestamp] => 2007-01-14 19:59:01 )
	echo "<table cellspacing='20'><tr><td valign='top'>";
	
	$nr = 0;
	$rows[$nr]["Statistic"] = "Total Users";
	$rows[$nr++]["Value"] = $dat["users"];
	
	$rows[$nr]["Statistic"] = "Users Never Logged In";
	$rows[$nr++]["Value"] = $dat["users"] - $dat["visit_users"];
	
	$rows[$nr]["Statistic"] = "% Active Users";
	$rows[$nr++]["Value"] = sprintf( "%2.1f%%", $dat["visit_users"] * 100 / $dat["users"] );
	
	$rows[$nr]["Statistic"] = "Ave. Days Since Last Visit";
	$rows[$nr++]["Value"] = sprintf( "%2.1f days", $dat["days_since"] / $dat["visit_users"] );
	LibcsTableShowresults::showResultsNoCounts( $rows, userGetDarkColor(), userGetLightColor(), "Website Registered User Statistics" );
	
	echo "</td></tr></table>";
}
function time_varying_membership_counts_report( $action,$data )
{
//	note: no output to screen can happen before jpgraph outputs the graph image - echo $data["name"] . "<br />";
	
	require_once("lib/php/jpgraph/jpgraph.php");
	require_once("lib/php/jpgraph/jpgraph_line.php");
	require_once("lib/php/jpgraph/jpgraph_date.php");

	$now = "as of " . LibcsDatesStatic::getTimeStampNowHuman();
	
	// Create a data set in range (50,70) and X-positions
	$start = strtotime("1 January 2007");
	$end = time();

	$db = JFactory::getDBO();
	$sql = "SELECT * FROM `#__cs_report_data` WHERE ( `report_type`='mem' OR `report_type`='memnew' ) AND 'datetimestamp' >= '2007-01-01' ORDER BY `id` ASC";
	$db->setQuery($sql);
	$rdat = $db->loadAssocList();
	
	$stats = array();
	$stats["min"] = 999999;
	$stats["min_date"] = "2109-00-00";
	$stats["max"] = 0;
	$stats["max_date"] = "1234-56-78";
	$stats["cur"] = 0;
	$stats["cur_date"] = "1234-56-78";
	// todo: could do average too

	$ndatapoints = count($rdat);
	$data = array();
	$xdata = array();
	$a = "";
	$d = "";

	for( $i=0; $i < $ndatapoints; ++$i )
	{
		$tmp = unserialize( $rdat[$i]["report_data"] );
		$data[$i] = $a = $tmp["active_paid"];
		$xdata[$i] = LibcsDatesStatic::getUnixTimestampFromMysql( $d = $rdat[$i]["datetimestamp"] );
		$d = substr ( $d, 0, 10 );

		// collect min and max stats and times
		if ( $a < $stats["min"] )
		{
			$stats["min"] = $a;
			$stats["min_date"] = $d;
		}
		if ( $a > $stats["max"] )
		{
			$stats["max"] = $a;
			$stats["max_date"] = $d;
		}
	}
	$stats["cur"] = $a;
	$stats["cur_date"] = $d;
	
	// Create the new graph
	$graph = new Graph(600,400);

	// Slightly larger than normal margins at the bottom to have room for
	// the x-axis labels
	$graph->SetMargin(70,10,30,90);
	
	// Fix the Y-scale to go between [0,500] and use date for the x-axis
	$graph->SetScale('datlin',0,260);
	$graph->SetMarginColor('#FFFFFF');	// dark orange (changed to white for better printing/newsletter usage)
	$graph->title->Set( userGetOrgAbbr() . " Paid Up Members $now");
	$graph->title->SetFont(FF_ARIAL,FS_BOLD,12);
	$graph->title->SetPos(0.2,0.98,'center','top');
	
	// Set the angle for the labels to 45 degrees
	$graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,10);
	$graph->xaxis->SetLabelAngle(45);
	
	// It is possible to adjust the density for the X-axis as well
	// The following call makes the dates a little more sparse
	// $graph->SetTickDensity(TICKD_NORMAL,TICKD_SPARSE);
	
	// The automatic format string for dates can be overridden
	//$graph->xaxis->scale->SetDateFormat('M-d-Y');
	$graph->xaxis->scale->SetDateFormat('m/Y');
	$graph->xaxis->scale->SetDateAlign(DAYADJ_1);
	
	// Adjust the start/end to a specific alignment
	//$graph->xaxis->scale->SetTimeAlign(MINADJ_15);
	
	//$light_color = '#FFC020'; // isea yellow
	$light_color_darker = '#2AC020'; // isea yellow
	$dark_color = '#e79104'; // lighter orange
	
	//$graph->SetColor($dark_color);
	
	$line = new LinePlot($data,$xdata);
	//$line->SetColor($light_color_darker);

	$legend = sprintf( "Members: Minimum: %s on %s, Maximum: %s on %s, Latest: %s on %s",
			$stats["min"],$stats["min_date"], $stats["max"],$stats["max_date"], $stats["cur"],$stats["cur_date"]);
	$line->SetLegend($legend);
	
	$graph->legend->SetPos(0.5,0.98,'center','bottom');
	
	$graph->Add($line);
	$graph->Stroke();
}
function userGetOrgAbbr()
{
	// rrtodo: document component dependency com_cs_datavault now dependent on com_cs_payments
	return JComponentHelper::getParams("com_cs_payments")->get("org_name_abbr","org_name_abbr");
}
function userGetLightColor()    // todo: configure required!
{
	return "#ffcc00";       // yellow
}
function userGetDarkColor()     // todo: configure required!
{
	//return "#d04004";     // dark orange
	return "#fc5107";       // lighter orange
}
function membership_report($action,$data)
{
	$db = JFactory::getDBO();
	$sql = "SELECT id,report_type,datetimestamp,report_data FROM `#__cs_report_data` WHERE `report_type` = 'memnew' ORDER BY `id` DESC LIMIT 1";
	$db->setQuery($sql);
	$dat = $db->loadAssoc();
	if ( ! isset( $dat["datetimestamp"] ) )
	{
		echo "no $action row in DB";
		jexit();
	}
	$dat = unserialize($dat["report_data"]);
echo <<<EOT
<html>
<head>
<style type="text/css">
body {
  font-family: Arial, Verdana, "Trebuchet MS", serif;
}
</style>
</head>
<body>
EOT;
	// report title
	$ts = $dat["datetimestamp"];
	$org = userGetOrgAbbr();
	echo<<<EOT
<table>
	<tr>
		<td width="80" rowspan='2'>
			<img src='/images/site/report-logo.jpg'>
		</td>
	</tr>
	<tr>
		<td>
			<span style="font-size: 120%;font-weight: bold;">$org Membership Report</span><br>
			as of $ts</h4>
		</td>
	</tr>
</table>
EOT;
	echo "<table cellspacing='20'><tr><td valign='top'>";
	
	// find membership counts in data array
	$mt = array();
	foreach ( $dat as $k => $v )
	{
		if ( strncmp( $k, "mtc_", 4 ) == 0 )
			$mt[substr( $k, 4 )]["c"] = $v;
		else if ( strncmp( $k, "mtp_", 4 ) == 0 )
			$mt[substr( $k, 4 )]["p"] = $v;
	}
	ksort( $mt );
	$rows = array();
	$nr = 0;
	$ct = 0;
	$pt = 0;
	foreach ( $mt as $k => $v )
	{
		$rows[$nr]["Membership Type"] = $k;
		$rows[$nr]["Count"] = $v["c"];
		$ct += $v["c"];
		$rows[$nr++]["Paid Up"] = $v["p"];
		$pt += $v["p"];
	}
	$rows[$nr]["Membership Type"] = "Totals";
	$rows[$nr]["Count"] = $ct;
	$rows[$nr++]["Paid Up"] = $pt;
	
	LibcsTableShowresults::showResults( $rows, userGetDarkColor(), userGetLightColor(), "Count of Members by Type" );
	
	echo "</td><td valign='top'>";
	
	$rows = array();
	$nr = 0;
	$rows[$nr]["Statistic"] = "% Paid Up";
	$rows[$nr++]["Value"] = sprintf( "%2.1f%%", $pt * 100 / $ct );
	$rows[$nr]["Statistic"] = "Avg. Paid Up Days";
	$rows[$nr++]["Value"] = sprintf( "%2.1f days", $dat["paid_factor"] / ( $dat["active_paid"] - $dat["paid_skipped"] ) );
	$rows[$nr]["Statistic"] = "% With Email";
	$rows[$nr++]["Value"] = sprintf( "%2.1f%%", $dat["email"] * 100 / $ct );
	
	LibcsTableShowresults::showResults( $rows, userGetDarkColor(), userGetLightColor(), "Other Statistics" );
	
	echo "</td></tr></table>";
		/*
		 array(23) {
		 reciprocal => int 7
		 active_paid => int 395
		 mtc_Senior => int 30
		 paid_factor => float 59799
		 mtc_Family => int 61
		 mtp_Family => int 49
		 mtc_BusinessStd => int 31
		 mtp_BusinessStd => int 23
		 mtc_LifetimeInd => int 2
		 mtp_LifetimeInd => int 2
		 mtc_Individual => int 254
		 mtp_Individual => int 204
		 mtp_Senior => int 21
		 mtc_BusinessPremier => int 6
		 mtp_BusinessPremier => int 6
		 mtc_Gift Ind => int 5
		 mtc_Student => int 5
		 mtp_Student => int 3
		 mtp_Gift Ind => int 4
		 mtc_BusinessCharter => int 1
		 mtp_BusinessCharter => int 1
		 paid_skipped => int 2
		 datetimestamp => string(19) 2006-12-30 19:31:02
		 */
}
echo "<h2>Reports</h2><p><strong>Click a report link and a new window/tab will open to display it.</strong></p>";

foreach( $reports as $report_action => $data )
{
	echo "<p>" . getReportURL($report_action,$data) . "</p>";
}
function validAction($action,$reports)
{
	foreach($reports as $report_action => $v )
		if ( $action == $report_action )
			return true;
	return false;
}
function getReportURL($report_action,$data)
{
	$report_name = $data["name"];
	
	$url = "/index.php?option=com_cs_datavault&view=reportdatas&action=$report_action";
	$a = "<a href='$url' target='_blank'>$report_name</a>";
	return $a; 
}
/*
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

$user       = Factory::getUser();
$userId     = $user->get('id');
$listOrder  = $this->state->get('list.ordering');
$listDirn   = $this->state->get('list.direction');
$canCreate  = $user->authorise('core.create', 'com_cs_datavault') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'reportdataform.xml');
$canEdit    = $user->authorise('core.edit', 'com_cs_datavault') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'reportdataform.xml');
$canCheckin = $user->authorise('core.manage', 'com_cs_datavault');
$canChange  = $user->authorise('core.edit.state', 'com_cs_datavault');
$canDelete  = $user->authorise('core.delete', 'com_cs_datavault');

// Import CSS
$document = Factory::getDocument();
$document->addStyleSheet(Uri::root() . 'media/com_cs_datavault/css/list.css');
?>

<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">

	
        <div class="table-responsive">
	<table class="table table-striped" id="reportdataList">
		<thead>
		<tr>
			<?php if (isset($this->items[0]->state)): ?>
				
			<?php endif; ?>

							<th class=''>
				<?php echo JHtml::_('grid.sort',  'COM_CS_DATAVAULT_REPORTDATAS_ID', 'a.id', $listDirn, $listOrder); ?>
				</th>
				<th class=''>
				<?php echo JHtml::_('grid.sort',  'COM_CS_DATAVAULT_REPORTDATAS_DATETIMESTAMP', 'a.datetimestamp', $listDirn, $listOrder); ?>
				</th>
				<th class=''>
				<?php echo JHtml::_('grid.sort',  'COM_CS_DATAVAULT_REPORTDATAS_REPORT_TYPE', 'a.report_type', $listDirn, $listOrder); ?>
				</th>
				<th class=''>
				<?php echo JHtml::_('grid.sort',  'COM_CS_DATAVAULT_REPORTDATAS_REPORT_DATA', 'a.report_data', $listDirn, $listOrder); ?>
				</th>


							<?php if ($canEdit || $canDelete): ?>
					<th class="center">
				<?php echo JText::_('COM_CS_DATAVAULT_REPORTDATAS_ACTIONS'); ?>
				</th>
				<?php endif; ?>

		</tr>
		</thead>
		<tfoot>
		<tr>
			<td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
		</tfoot>
		<tbody>
		<?php foreach ($this->items as $i => $item) : ?>
			<?php $canEdit = $user->authorise('core.edit', 'com_cs_datavault'); ?>

			
			<tr class="row<?php echo $i % 2; ?>">

				<?php if (isset($this->items[0]->state)) : ?>
					<?php $class = ($canChange) ? 'active' : 'disabled'; ?>
					
				<?php endif; ?>

								<td>

					<?php echo $item->id; ?>
				</td>
				<td>

					<?php echo $item->datetimestamp; ?>
				</td>
				<td>

					<?php echo $item->report_type; ?>
				</td>
				<td>

					<?php echo $item->report_data; ?>
				</td>


								<?php if ($canEdit || $canDelete): ?>
					<td class="center">
					</td>
				<?php endif; ?>

			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
        </div>
	<?php if ($canCreate) : ?>
		<a href="<?php echo Route::_('index.php?option=com_cs_datavault&task=reportdataform.edit&id=0', false, 0); ?>"
		   class="btn btn-success btn-small"><i
				class="icon-plus"></i>
			<?php echo Text::_('COM_CS_DATAVAULT_ADD_ITEM'); ?></a>
	<?php endif; ?>

	<input type="hidden" name="task" value=""/>
	<input type="hidden" name="boxchecked" value="0"/>
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"/>
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"/>
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php if($canDelete) : ?>
<script type="text/javascript">

	jQuery(document).ready(function () {
		jQuery('.delete-button').click(deleteItem);
	});

	function deleteItem() {

		if (!confirm("<?php echo Text::_('COM_CS_DATAVAULT_DELETE_MESSAGE'); ?>")) {
			return false;
		}
	}
</script>
<?php endif; ?>
*/