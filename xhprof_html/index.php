<?php
if (!defined('XHPROF_LIB_ROOT')) {
	define('XHPROF_LIB_ROOT', dirname(dirname(__FILE__)) . '/xhprof_lib');
}
require_once(XHPROF_LIB_ROOT . "/config.php");
require_once(XHPROF_LIB_ROOT . '/display/xhprof.php');
require_once(XHPROF_LIB_ROOT . "/utils/common.php");

if (false !== $controlIPs && !in_array($_SERVER['REMOTE_ADDR'], $controlIPs)) {
	die("You do not have permission to view this page.");
}

unset($controlIPs);

// param name, its type, and default value
$params = array('run' => array(XHPROF_STRING_PARAM, ''),
	'wts' => array(XHPROF_STRING_PARAM, ''),
	'symbol' => array(XHPROF_STRING_PARAM, ''),
	'sort' => array(XHPROF_STRING_PARAM, 'wt'), // wall time
	'run1' => array(XHPROF_STRING_PARAM, ''),
	'run2' => array(XHPROF_STRING_PARAM, ''),
	'run3' => array(XHPROF_STRING_PARAM, ''),
	'run4' => array(XHPROF_STRING_PARAM, ''),
	'run5' => array(XHPROF_STRING_PARAM, ''),
	'run6' => array(XHPROF_STRING_PARAM, ''),
	'run7' => array(XHPROF_STRING_PARAM, ''),
	'run8' => array(XHPROF_STRING_PARAM, ''),
	'run9' => array(XHPROF_STRING_PARAM, ''),
	'source' => array(XHPROF_STRING_PARAM, 'xhprof'),
	'all' => array(XHPROF_UINT_PARAM, 0),
);

// pull values of these params, and create named globals for each param
xhprof_param_init($params);

/* reset params to be a array of variable names to values
   by the end of this page, param should only contain values that need
   to be preserved for the next page. unset all unwanted keys in $params.
 */
foreach ($params as $k => $v) {
	$params[$k] = $$k;

	// unset key from params that are using default values. So URLs aren't
	// ridiculously long.
	if ($params[$k] == $v[1]) {
		unset($params[$k]);
	}
}


$vbar = ' class="vbar"';
$vwbar = ' class="vwbar"';
$vwlbar = ' class="vwlbar"';
$vbbar = ' class="vbbar"';
$vrbar = ' class="vrbar"';
$vgbar = ' class="vgbar"';

$xhprof_runs_impl = new XHProfRuns_Default();

$domainFilter = getFilter('domain_filter');
$serverFilter = getFilter('server_filter');

$domainsRS = $xhprof_runs_impl->getDistinct(array('column' => 'server name'));
$domainFilterOptions = array("None");
while ($row = XHProfRuns_Default::getNextAssoc($domainsRS)) {
	$domainFilterOptions[] = $row['server name'];
}

$serverRS = $xhprof_runs_impl->getDistinct(array('column' => 'server_id'));
$serverFilterOptions = array("None");
while ($row = XHProfRuns_Default::getNextAssoc($serverRS)) {
	$serverFilterOptions[] = $row['server_id'];
}

$criteria = array();
if (!is_null($domainFilter)) {
	$criteria['server name'] = $domainFilter;
}
if (!is_null($serverFilter)) {
	$criteria['server_id'] = $serverFilter;
}
$_xh_header = "";
if (isset($_GET['run1']) || isset($_GET['run'])) {
	include("../xhprof_lib/templates/header.phtml");
	displayXHProfReport($xhprof_runs_impl, $params, $source, $run, $wts,
		$symbol, $run1, $run2);
} elseif (isset($_GET['geturl'])) {
	$last = (isset($_GET['last'])) ? $_GET['last'] : 100;
	$last = (int)$last;
	$criteria['url'] = $_GET['geturl'];
	$criteria['limit'] = $last;
	$criteria['order by'] = 'timestamp';
	$rs = $xhprof_runs_impl->getUrlStats($criteria);
	list($header, $body) = showChart($rs, false);
	$_xh_header .= $header;

	include("../xhprof_lib/templates/header.phtml");
	$rs = $xhprof_runs_impl->getRuns($criteria);
	include("../xhprof_lib/templates/emptyBody.phtml");

	$url = $_GET['geturl'];
	displayRuns($rs, "Runs with URL: $url");
} elseif (isset($_GET['getcurl'])) {
	$last = (isset($_GET['last'])) ? $_GET['last'] : 100;
	$last = (int)$last;
	$criteria['c_url'] = $_GET['getcurl'];
	$criteria['limit'] = $last;
	$criteria['order by'] = 'timestamp';

	$rs = $xhprof_runs_impl->getUrlStats($criteria);
	list($header, $body) = showChart($rs, false);
	$_xh_header .= $header;
	include("../xhprof_lib/templates/header.phtml");

	$url = urlencode($_GET['getcurl']);
	$rs = $xhprof_runs_impl->getRuns($criteria);
	include("../xhprof_lib/templates/emptyBody.phtml");
	displayRuns($rs, "Runs with Simplified URL: $url");
} elseif (isset($_GET['getruns'])) {
	include("../xhprof_lib/templates/header.phtml");
	$days = (int)$_GET['days'];

	switch ($_GET['getruns']) {
		case "cpu":
			$load = "cpu";
			break;
		case "wt":
			$load = "wt";
			break;
		case "pmu":
			$load = "pmu";
			break;
	}

	$criteria['order by'] = $load;
	$criteria['limit'] = "500";
	$criteria['where'] = "DATE_SUB(CURDATE(), INTERVAL $days DAY) <= `timestamp`";
	$rs = $xhprof_runs_impl->getRuns($criteria);
	displayRuns($rs, "Worst runs by $load");
} elseif (isset($_GET['hit'])) {
	include("../xhprof_lib/templates/header.phtml");
	$last = (isset($_GET['hit'])) ? $_GET['hit'] : 25;
	$last = (int)$last;
	$days = (isset($_GET['days'])) ? $_GET['days'] : 1;
	$days = (int)$days;
	if (isset($_GET['type']) && ($_GET['type'] === 'url' OR $_GET['type'] = 'curl')) {
		$type = $_GET['type'];
	} else {
		$type = 'url';
	}

	$criteria['limit'] = $last;
	$criteria['days'] = $days;
	$criteria['type'] = $type;
	$resultSet = $xhprof_runs_impl->getHardHit($criteria);

	echo "<div class=\"runTitle\">Hardest Hit</div>\n";
	echo "<table id=\"box-table-a\" class=\"tablesorter\" summary=\"Stats\"><thead><tr><th>URL</th><th>Hits</th><th class=\"{sorter: 'numeric'}\">Total Wall Time</th><th>Avg Wall Time</th></tr></thead>";
	echo "<tbody>\n";
	while ($row = XHProfRuns_Default::getNextAssoc($resultSet)) {
		$url = urlencode($row['url']);
		$html['url'] = htmlentities($row['url'], ENT_QUOTES, 'UTF-8');
		echo '   <tr><td><a href="?geturl=' . $url . '">' . $html['url'] . '</a></td><td>' .
			$row['count'] . '</td><td title="' . $row['total_wall'] . '" >' .
			printSeconds($row['total_wall']) . '</td><td title="' . $row['avg_wall'] . '">' .
			printSeconds($row['avg_wall']) . '</td></tr>' . PHP_EOL;
	}
	echo "</tbody>\n";
	echo "</table>\n";
} else {
	include("../xhprof_lib/templates/header.phtml");
	$last = (isset($_GET['last'])) ? $_GET['last'] : 25;
	$last = (int)$last;
	$criteria['order by'] = "timestamp";
	$criteria['limit'] = $last;
	$rs = $xhprof_runs_impl->getRuns($criteria);
	displayRuns($rs, "Last $last Runs");
}

include("../xhprof_lib/templates/footer.phtml");
