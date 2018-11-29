<?
$mysql_hostname = "localhost"; 
$mysql_user = ""; 
$mysql_password = ""; 
$mysql_database = "rwa_data"; 
$mcol = "mysql:host=$mysql_hostname;dbname=$mysql_database"; 
$mdb=null;

function dbConnect() {
	global $mysql_user;
	global $mysql_password;
	$mdb = new PDO($mcol , $mysql_user, $mysql_password); 
	$mdb->exec("SET NAMES 'utf8';");
	$mdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
}


function msqlQueryFetchAll($query, $arguments=NULL) {
	global $mdb;
	if(null==$mdb)
		dbConnect();

	$stmt=$mdb->prepare ($query);
	$stmt->execute( $arguments );
	return $stmt->fetchall(PDO::FETCH_ASSOC);
}

function msqlQueryAll($query, $arguments=NULL) {
	global $mdb;
	if(null==$mdb)
		dbConnect();
	$stmt=$mdb->prepare ($query);
	$stmt->execute( $arguments );
}
function getOneNode($xpath, $query) {
	$resultList=$xpath->query($query);
	if(1!=count($resultList))
		throw new Exception("Need exactly one result for '$query', instead `".  count($resultList) ."` found");
	return $resultList->item(0);
}

function xmlLoad($args) {
	$res=array();
	$scrf= $_SERVER['SCRIPT_FILENAME'];
	$localPath=substr($scrf, 0, strpos($scrf,'index')); 
	$local_file_name=$localPath.gethostname().".xml";
	if(!file_exists($local_file_name))
		throw new Exception("`$local_file_name` not found");
	$m_doc = new DOMDocument();
	if(FALSE === $m_doc->load( $local_file_name ))
		throw new Exception("Error loading `$local_file_name`");
	$m_xpath = new DOMXpath($m_doc);
	$res['xml_contents'] = file_get_contents($local_file_name);
	$res['db_user_name']=getOneNode($m_xpath, "//machine/databases/db_credentials/db_user_name")->getAttribute("value");
	$res['db_user_password']=getOneNode($m_xpath, "//machine/databases/db_credentials/db_user_password")->getAttribute("value");
	$query="//machine/databases/db_list/one_db";
	$resultList=$m_xpath->query($query);
	$dbList=array();
	foreach($resultList as $oneNode)
		$dbList[] = $oneNode->getAttribute('name');
	$res['db_list']=$dbList;
	return $res;
}
function xmlSave($args) {
	$scrf= $_SERVER['SCRIPT_FILENAME'];
	$localPath=substr($scrf, 0, strpos($scrf,'index')); 
	$local_file_name=$localPath.gethostname().".xml";
	$fileW = fopen($local_file_name,"w");
	fwrite( $fileW, $args['rtext']);
	fclose($fileW);
	if(!file_exists($local_file_name))
		throw new Exception("cannot write to `$local_file_name`");
}
function getXML($args) {
	$ddoc = new DOMDocument("1.0","utf-8");
	$ddoc->formatOutput = true;
	$todayDate=date("Y-m-d");

	$machine = $ddoc->createElement('machine');
	$machine->setAttribute("name", gethostname());
	$ddoc->appendChild($machine);
	$dbNode = $ddoc->createElement('databases');
	$machine->appendChild($dbNode);

	$bNode = $ddoc->createElement('db_credentials');
	$dbNode->appendChild($bNode);

	$cNode = $ddoc->createElement('db_user_name');
	$cNode->setAttribute('value', $args['dbUser']);
	$bNode->appendChild($cNode);

	$cNode = $ddoc->createElement('db_user_password');
	$cNode->setAttribute('value', $args['dbPwd']);
	$bNode->appendChild($cNode);

	$bNode = $ddoc->createElement('db_list');
	$bNode->setAttribute("count", count($args['dbList']));
	$dbNode->appendChild($bNode);
	foreach($args['dbList'] as $oneDb) {
		$cNode = $ddoc->createElement('one_db');
		$cNode->setAttribute('name', $oneDb);
		$bNode->appendChild($cNode);
	}

	$bNode = $ddoc->createElement('folders');
	$machine->appendChild($bNode);
	foreach($args['foldersList'] as $oneDb) {
		$cNode = $ddoc->createElement('folder');
		$cNode->setAttribute('name', $oneDb);
		$bNode->appendChild($cNode);
	}

	return $ddoc->saveXML();
}
function listDatabases($args) {
	$mysql_user = $args['dbUser'];
	$mysql_password = $args['dbPwd'];
	$mdb = new PDO("mysql:host=$mysql_hostname;" , $mysql_user, $mysql_password); 
	$stmt=$mdb->prepare ("show databases;");
	$stmt->execute(  );
	return $stmt->fetchall(PDO::FETCH_ASSOC);
}


$dbUser="";
$dbPwd="";

try {
	if($_SERVER["REQUEST_METHOD"] == "POST" ) {
		if(array_key_exists('ajx', $_POST)) {
		$arrayData=$_POST['ajx'];
		$verb=$arrayData['verb'];
// AJAX		
try {
			switch($verb) {
				case 'xmlLoad':
					$res=xmlLoad($arrayData);
				break;
				case 'xmlSave':
					$res=xmlSave($arrayData);
				break;
				case 'showXml':
					$res=getXML($arrayData);
				break;
				case 'listDatabases':
					$res=listDatabases($arrayData);
				break;
				default:
					throw new Exception("Invalid verb `$verb`");
				break;
			}
        		header('Content-Type: application/json; charset=UTF-8');
        		die (json_encode($res));
} catch(Exception $exc) {
	error_log($exc->getMessage());
        header('HTTP/1.1 500 Server Exception');
        header('Content-Type: application/json; charset=UTF-8');
        $result=array();
        $result['messages'] = $exc->getMessage();
        die(json_encode($result));
}
		} else {
// form post
/*
array(5) {
  ["dbUser"]=>
  string(4) "root"
  ["dbPwd"]=>
  string(8) "chetro93"
  ["listStoredDB"]=>
  array(2) {
    [0]=>
    string(10) "italianaDb"
    [1]=>
    string(8) "rwa_data"
  }
  ["addFolder"]=>
  string(12) "/etc/openvpn"
  ["listFolders"]=>
  array(2) {
    [0]=>
    string(12) "/etc/apache2"
    [1]=>
    string(12) "/etc/openvpn"
  }
}
*/
			$dbUser=$_POST['dbUser'];
			$dbPwd=$_POST['dbPwd'];
		}
	}
} catch(Exception $exc) {
	error_log($exc->getMessage());
        header('HTTP/1.1 500 Server Exception');
        header('Content-Type: application/json; charset=UTF-8');
        $result=array();
        $result['messages'] = $exc->getMessage();
        die(json_encode($result));
}


?>
<!doctype html>
<html lang="en" class="ltr" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>NITRO Backup</title>
</head>
<script type="text/javascript" src="/js/jquery.min.js"></script>
<script language="javascript" type="text/javascript">
function alertRetFalse(msg) {
	alert(msg);
	return false;
}

function ajxAlrRetFls(data) { 
	console.log(data);

	var msg=data.statusText;
	if(0<data.responseText.length) {
		var mob=JSON.parse(data.responseText);
		msg=msg+'; '+mob.messages;
	}
	return alertRetFalse(msg);
}
function onDelFolder() {
	var	listFolders=$('#listFolders')
	,	folderToDelete=listFolders.val()
	;
	if(null==folderToDelete)
		return alertRetFalse('Please select an item first');
	listFolders.find('option[value="'+folderToDelete+'"]').remove();
}
function onchkConBtn() {
	var	dbUser=$('#dbUser').val()
	,	dbPwd=$('#dbPwd').val()
	,	arrVal={}
	;
	arrVal['verb']='listDatabases';
	arrVal['dbUser']=dbUser;
	arrVal['dbPwd']=dbPwd;

	$.ajax({
		url: "index.php",
		type: "POST",
		data: {	'ajx' : arrVal },
		success: function(data){
			$('#listDB').empty();
			$( data ).each(function(index, dbData){
				$('#listDB').append(new Option(dbData.Database, dbData.Database));
			});
		},
		error: function(data) { 
			return ajxAlrRetFls(data);
		}
	});
}
function onAddFolder() {
	var	folderToAdd=$('#addFolder').val()
	,	listFolders=$('#listFolders')
	;
	$('#listFolders').append(new Option(folderToAdd, folderToAdd));
}
function onAddDbToList() {
	var	listDB=$('#listDB')
	,	val=listDB.val()
	,	ttext=$( "#listDB option:selected" ).text();
	;
	if(null==val)
		return alertRetFalse('Please select an item first');
	$('#listStoredDB').append(new Option(ttext, val));
	listDB.find('option[value="'+val+'"]').remove();
}
function onRmDbFroList() {
	var	listStoredDB=$('#listStoredDB')
	,	val=listStoredDB.val()
	,	ttext=$( "#listStoredDB option:selected" ).text();
	;
	if(null==val)
		return alertRetFalse('Please select an item first');
	$('#listDB').append(new Option(ttext, val));
	listStoredDB.find('option[value="'+val+'"]').remove();
}
function onXMLLoad() {
	var	arrVal={}
	;
	arrVal['verb']='xmlLoad';
	$.ajax({
		url: "index.php",
		type: "POST",
		data: {	'ajx' : arrVal },
		success: function(data){
console.log(data);
			$('#dbUser').val(data.db_user_name);
			$('#dbPwd').val(data.db_user_password);
			$('#texta').val(data.xml_contents);
		},
		error: function(data) { 
			return ajxAlrRetFls(data);
		}
	});
}
function onXMLSave() {
	var	texta=$('#texta').val()
	,	arrVal={}
	;
	arrVal['verb']='xmlSave';
	arrVal['rtext']=texta;
	$.ajax({
		url: "index.php",
		type: "POST",
		data: {	'ajx' : arrVal },
		success: function(data){
			alert('SUCCESS');
		},
		error: function(data) { 
			return ajxAlrRetFls(data);
		}
	});
}
function onShowXML() {
	var	dbUser=$('#dbUser').val()
	,	dbPwd=$('#dbPwd').val()
	,	texta=$('#texta')
	,	arrVal={}
	,	listStoredDB=$('#listStoredDB')
	,	ind
	,	DBs=[]
	,	bFolders=[]
	;
	arrVal['dbUser']=dbUser;
	arrVal['dbPwd']=dbPwd;
	$('#listStoredDB option').each(function(){ 
		DBs.push( $(this).text() );
	});
	arrVal['dbList']=DBs;
	$('#listFolders option').each(function(){ 
		bFolders.push( $(this).text() );
	});
	arrVal['foldersList']=bFolders;
	arrVal['verb']='showXml';
	$.ajax({
		url: "index.php",
		type: "POST",
		data: {	'ajx' : arrVal },
		success: function(data){
			texta.val(data);
		},
		error: function(data) { 
			return ajxAlrRetFls(data);
		}
	});
}
function onSaveCFG() {
	var sBx= document.getElementById('listStoredDB');
	for (var i = 0; i < sBx.options.length; i++) {
		sBx.options[i].selected = true;
	}
	sBx= document.getElementById('listFolders');
	for (var i = 0; i < sBx.options.length; i++) {
		sBx.options[i].selected = true;
	}
	return true;
}
</script>
<body>

<div id=leftPane style='width:48%;float:left;'>
<form action='#' id='uplData' name='uplData' method='post' enctype='multipart/form-data' onsubmit='return checkform()' >
<h3>This machine name: <?=gethostname()?> </h3>
<div style="clear:both"></div>
<fieldset>
<legend>DATABASES</legend>
<p>User: <input type=text name=dbUser id=dbUser value=<?=$dbUser?>> </p>
<p>Password: <input type=text name=dbPwd id=dbPwd value=<?=$dbPwd?>></p>
<p><input type=button id='chkConBtn' onClick='onchkConBtn()' value='Check Connection'> </p>
<div style='float:left;'>
	<p>Currently defined databases:</p>
	<select multiple=multiple size=15 name=listDB id=listDB ondblclick='onAddDbToList()'></select>
</div>
<div style='float:left;'>
	<br /> <br /> <br /> <br /> <br /> <br /> <br /> <br /> <br />
	<p>
	<input type=button id='addDbToList' onClick='onAddDbToList()' value='Add ->'>
	</p> <p>
	<input type=button id='rmDbFroList' onClick='onRmDbFroList()' value='<- Del'>
	</p>
</div>
<div style='float:left;'>
	<p>Databases being stored:</p>
	<select multiple=multiple size=15 name='listStoredDB[]' id=listStoredDB ondblclick='onRmDbFroList()'></select>
</div>
</fieldset>

<fieldset>
<legend>FOLDERS</legend>
<p>This folder <input type=text name=addFolder id=addFolder>
<input type=button id='addFolderButton' onClick='onAddFolder()' value='Add'> </p>
<p>Folders being stored:</p>
<select multiple=multiple size=15 name=listFolders[] id=listFolders></select>
<p><input type=button id='delFolderButton' onClick='onDelFolder()' value='Delete selected folder'></p>
<p></p>
</fieldset>
<p><input type=submit id='saveCfg' onClick='onSaveCFG()' value='Save configuration'></p>
<p><input type=submit id='loadCfg' onClick='onLoadCFG()' value='Load configuration'></p>
</form>
</div>
<div id=rightPane style='width:48%;float:left;'>
<p>XML:
<input type=button id='showXml' onClick='onShowXML()' value='Create'> 
<input type=button id='xmlSave' onClick='onXMLSave()' value='Save'> 
<input type=button id='xmlLoad' onClick='onXMLLoad()' value='Load'> 
</p>
<textarea style='width:100%' id=texta name=texta rows=50></textarea>
</div>
<div style="clear:both"></div>

<a href='#'>Reset</a>
</body>
</html>
