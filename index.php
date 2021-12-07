<?

function query_node($xpath, $query) {
	$q_results=$xpath->query($query);
	if(1!=count($q_results))
		throw new Exception("Need exactly one result for '$query', instead `".  count($q_results) ."` found");
	return $q_results->item(0);
}

function xmlLoad($args) {
	$res=array();
	$script_fn= $_SERVER['SCRIPT_FILENAME'];
	$local_path=substr($script_fn, 0, strpos($script_fn,'index')); 
	$local_file_name=$local_path.gethostname().".xml";
	if(!file_exists($local_file_name))
		throw new Exception("`$local_file_name` not found");
	$m_doc = new DOMDocument();
	if(FALSE === $m_doc->load( $local_file_name ))
		throw new Exception("Error loading `$local_file_name`");
	$m_xpath = new DOMXpath($m_doc);
	$res['xml_contents'] = file_get_contents($local_file_name);
	$res['bf_home_dir']=query_node($m_xpath, "//machine")->getAttribute("bf_home_dir");
	$res['old_files_age']=query_node($m_xpath, "//machine")->getAttribute("old_files_age");
	$res['rsync_to']=query_node($m_xpath, "//machine")->getAttribute("rsync_to");
	$res['bfi']=query_node($m_xpath, "//machine/databases/db_credentials/db_user_name")->getAttribute("value");
	$res['db_user_name']=query_node($m_xpath, "//machine/databases/db_credentials/db_user_name")->getAttribute("value");
	$res['db_user_password']=query_node($m_xpath, "//machine/databases/db_credentials/db_user_password")->getAttribute("value");
	$query="//machine/databases/db_list/one_db";
	$q_results=$m_xpath->query($query);
	$dbList=array();
	foreach($q_results as $q_node)
		$dbList[] = $q_node->getAttribute('name');
	$res['db_list']=$dbList;
	$query="//machine/folders/folder";
	$q_results=$m_xpath->query($query);
	$foldersList=array();
	foreach($q_results as $q_node)
		$foldersList[] = $q_node->getAttribute('name');
	$res['folders_list']=$foldersList;
	return $res;
}
function createScript($args) {
	$xml_contents=create_xml($args);
	$dom_doc = new DOMDocument("1.0","utf-8");
	if(FALSE === $dom_doc->LoadXml( $xml_contents ))
		throw new Exception("Error reading XML from `$xml_contents` found");
	$m_xpath = new DOMXpath($dom_doc);
	$arrVal=array();
	$arrVal['bf_home_dir']=query_node($m_xpath, "//machine")->getAttribute("bf_home_dir");
	$arrVal['old_files_age']=query_node($m_xpath, "//machine")->getAttribute("old_files_age");
	$arrVal['rsync_to']=query_node($m_xpath, "//machine")->getAttribute("rsync_to");
	$arrVal['db_user_name']=query_node($m_xpath, "//machine/databases/db_credentials/db_user_name")->getAttribute("value");
	$arrVal['db_user_password']=query_node($m_xpath, "//machine/databases/db_credentials/db_user_password")->getAttribute("value");

	$q_results=$m_xpath->query( "//machine/databases/db_list/one_db" );
	$db_list="";
	foreach($q_results as $q_node)
		$db_list .= $q_node->getAttribute('name')." ";
	$arrVal['db_list']=$db_list;

	$q_results=$m_xpath->query( "//machine/folders/folder" );
	$folders_list="";
	foreach($q_results as $q_node) {
		$node=trim($q_node->getAttribute('name'), '/');
		$folders_list .= "$node ";
	}

	$arrVal['folders_list']=$folders_list;
	
$script_template='#!/bin/sh

# where to put the backup files
HOMEDIR="bf_home_dir"
#user for connecting to databases
DBUSER="db_user_name"
DBPWD="db_user_password"

# no need to change these
BACKUPD="/tmp"
MYHOST=$(hostname)
DATES=$(date "+%Y%m%d")
TARFILE="${DATES}.${MYHOST}.backup.tar"
TARFPATH=${HOMEDIR}/${TARFILE}
GZFILE="${TARFILE}.gz"
TIMESTAMPFILE=$MYHOST.TIMESTAMP.txt
DBLIST="db_list"

aLog() {
	echo "--- $*"
}

logExe() {
	COMAND="$*"
	aLog "$COMAND"
	eval "$COMAND"
}



bckpDB () {
	DBNAME=$1 
	SQLFILE=$2
	THEHOST=$3
	aLog "Dumping $DBNAME"
	mysqldump --host=$THEHOST --user=$DBUSER --password=$DBPWD $DBNAME > $SQLFILE
}

if [ ! -e $HOMEDIR ]; then
	mkdir $HOMEDIR
	if [ ! -e $HOMEDIR ]; then
		aLog "Home directory $HOMEDIR does not exist, cannot create"
		exit 255
	fi
fi

aLog "This is $0"
cd $HOMEDIR
aLog "PWD is:"
logExe pwd
date > $TIMESTAMPFILE
tar cf $TARFILE $TIMESTAMPFILE
for db in $DBLIST; do
	bckpDB $db $db.sql localhost
	tar uf $TARFILE $db.sql
	rm $db.sql
done
rm $TIMESTAMPFILE

cd /

for node in folders_list
	do
	if [ -e $node ]; then
		logExe "tar uf $TARFPATH $node"
	fi
done

cd $HOMEDIR
echo -n "Current folder: "
pwd

gzip -c $TARFILE > ${GZFILE}

logExe "ls -la"
aLog $LOGMSG

rm $TARFILE

# delete old files
logExe "find . -mtime +old_files_age -exec rm {} \;"

logExe "rsync -azvr --delete ${HOMEDIR}/ rsync_to"

';
	foreach($arrVal as $key => $val)
		$script_template = str_replace($key, $val, $script_template);
	$fileW = fopen('nitroBackup.sh',"w");
	fwrite($fileW, $script_template);
	fclose($fileW);
	return true;
}

function save_xml($args) {
	$script_fn= $_SERVER['SCRIPT_FILENAME'];
	$xml_contents=create_xml($args);
	$local_path=substr($script_fn, 0, strpos($script_fn,'index')); 
	$local_file_name=$local_path.gethostname().".xml";
	$fileW = fopen($local_file_name,"w");
	fwrite( $fileW, $xml_contents);
	fclose($fileW);
	if(!file_exists($local_file_name))
		throw new Exception("cannot write to `$local_file_name`");
}
function create_xml($args) {
	$dom_doc = new DOMDocument("1.0","utf-8");
	$dom_doc->formatOutput = true;
	$todayDate=date("Y-m-d");

	$machine = $dom_doc->createElement('machine');
	$machine->setAttribute("name", gethostname());
	$machine->setAttribute("bf_home_dir", $args['bf_home_dir']);
	$machine->setAttribute("old_files_age", $args['old_files_age']);
	$machine->setAttribute("rsync_to", $args['rsync_to']);
	$dom_doc->appendChild($machine);
	$dom_node = $dom_doc->createElement('databases');
	$machine->appendChild($dom_node);

	$child_node = $dom_doc->createElement('db_credentials');
	$dom_node->appendChild($child_node);

	$cchild_node = $dom_doc->createElement('db_user_name');
	$cchild_node->setAttribute('value', $args['dbUser']);
	$child_node->appendChild($cchild_node);

	$cchild_node = $dom_doc->createElement('db_user_password');
	$cchild_node->setAttribute('value', $args['dbPwd']);
	$child_node->appendChild($cchild_node);

	$child_node = $dom_doc->createElement('db_list');
	$child_node->setAttribute("count", count($args['dbList']));
	$dom_node->appendChild($child_node);
	foreach($args['dbList'] as $oneDb) {
		$cchild_node = $dom_doc->createElement('one_db');
		$cchild_node->setAttribute('name', $oneDb);
		$child_node->appendChild($cchild_node);
	}

	$child_node = $dom_doc->createElement('folders');
	$child_node->setAttribute("count", count($args['foldersList']));
	$machine->appendChild($child_node);
	foreach($args['foldersList'] as $oneDb) {
		$cchild_node = $dom_doc->createElement('folder');
		$cchild_node->setAttribute('name', $oneDb);
		$child_node->appendChild($cchild_node);
	}

	return $dom_doc->saveXML();
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
			$ajxParams=$_POST['ajx'];
			$funcName=$ajxParams['verb'];
			$res=$funcName($ajxParams);
       			header('Content-Type: application/json; charset=UTF-8');
       			die (json_encode($res));
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
    <title>NITRO Backup <?=gethostname()?></title>
</head>
<style>
fieldset {background-color:#cccccc;border:1px  #50a0a0 solid;padding:5px;margin:5px;}
textarea {background-color:#f0f0ff;border:1px  #50a0a0 solid;padding:5px;margin:5px;}
legend {border:1px #50a0a0 solid;background-color:#ccccff;font-weight: bold;}
h1 {color: blue;} // styles applied to h1 tag
p {color: red;} // styles applied to p tag
</style>
<script type="text/javascript" src="jquery.min.js"></script>
<script language="javascript" type="text/javascript">
function alertRetFalse(msg) {
	alert(msg);
	return false;
}

function ajxAlrRetFls(data) { 
	var msg=data.statusText;
	if(0<data.responseText.length) {
		var mob=JSON.parse(data.responseText);
		msg=msg+': '+mob.messages;
	}
	return alertRetFalse(msg);
}

function check_conn() {
	var	dbUser=$('#dbUser').val()
	,	dbPwd=$('#dbPwd').val()
	,	arrVal={}
	,	listDB=$('#listDB')
	,	listStoredDB=$('#listStoredDB')
	,	val
	;
	arrVal['verb']='listDatabases';
	arrVal['dbUser']=dbUser;
	arrVal['dbPwd']=dbPwd;

	$.ajax({
		url: "index.php",
		type: "POST",
		data: {	'ajx' : arrVal },
		success: function(data){
			listDB.empty();
			$( data ).each(function(index, dbData){
				val=dbData.Database;
				if( 0 == listStoredDB.find('option[value="'+val+'"]').length)
					listDB.append(new Option(val, val));
			});
		},
		error: function(data) { 
			return ajxAlrRetFls(data);
		}
	});
}
function folder_rm() {
	var	listFolders=$('#listFolders')
	,	folderToDelete=listFolders.val()
	;
	if(null==folderToDelete)
		return alertRetFalse('Please select an item first');
	listFolders.find('option[value="'+folderToDelete+'"]').remove();
	$('#addFolder').val(folderToDelete);
}
function folder_add() {
	var	folderInput=$('#addFolder')
	,	folderToAdd=folderInput.val()
	,	listFolders=$('#listFolders')
	;
	$('#listFolders').append(new Option(folderToAdd, folderToAdd));
	folderInput.val('');
}
function db_add() {
	var	listDB=$('#listDB')
	,	listStoredDB=$('#listStoredDB')
	,	val=listDB.val()
	,	ttext=$( "#listDB option:selected" ).text()
	;
	if(null==val)
		return alertRetFalse('Please select an item first');
	listStoredDB.find('option[value="'+val+'"]').remove();
	listStoredDB.append(new Option(ttext, val));
	listDB.find('option[value="'+val+'"]').remove();
}
function db_rm() {
	var	listStoredDB=$('#listStoredDB')
	,	val=listStoredDB.val()
	,	ttext=$( "#listStoredDB option:selected" ).text();
	;
	if(null==val)
		return alertRetFalse('Please select an item first');
	$('#listDB').append(new Option(ttext, val));
	listStoredDB.find('option[value="'+val+'"]').remove();
}
function xml_load() {
	var	arrVal={}
	,	oneDb
	,	exists
	,	listDB=$('#listDB')
	,	listStoredDB=$('#listStoredDB')
	,	listFolders = $('#listFolders')
	;
	arrVal['verb']='xmlLoad';
	listStoredDB.empty();
	listFolders.empty();
	$.ajax({
		url: "index.php",
		type: "POST",
		data: {	'ajx' : arrVal },
		success: function(data){
			$('#dbUser').val(data.db_user_name);
			$('#dbPwd').val(data.db_user_password);
			$('#texta').val(data.xml_contents);
			$('#bf_home_dir').val(data.bf_home_dir);
			$('#old_files_age').val(data.old_files_age);
			$('#rsync_to').val(data.rsync_to);
			var db_list=data.db_list;
			for(var db in db_list) {
				oneDb=db_list[db];
// check if it is in the left list
				if(listDB.find("option:contains('" + oneDb + "')").length){
					listDB.find('option[value="'+oneDb+'"]').remove();
				}
				listStoredDB.append(new Option(oneDb, oneDb));
			}
			var folders_list=data.folders_list;
			for(var db in folders_list) {
				oneDb=folders_list[db];
// check if it is in the left list
				listFolders.append(new Option(oneDb, oneDb));
			}
		},
		error: function(data) { 
			return ajxAlrRetFls(data);
		}
	});
}
function xml_save() {
	var	arrVal={}
	;
	arrVal=prepareArrayForXML();
	arrVal['verb']='save_xml';
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
function script_create(){
	var	arrVal={}
	;
	arrVal=prepareArrayForXML();
	arrVal['verb']='createScript';
	$.ajax({
		url: "index.php",
		type: "POST",
		data: {	'ajx' : arrVal },
		success: function(data){
			alert("SUCCESS");
		},
		error: function(data) { 
			return ajxAlrRetFls(data);
		}
	});
}
function prepareArrayForXML() {
	var	dbUser=$('#dbUser').val()
	,	dbPwd=$('#dbPwd').val()
	,	bf_home_dir=$('#bf_home_dir').val()
	,	old_files_age=$('#old_files_age').val()
	,	rsync_to=$('#rsync_to').val()
	,	texta=$('#texta')
	,	arrVal={}
	,	listStoredDB=$('#listStoredDB')
	,	ind
	,	DBs=[]
	,	bFolders=[]
	;
	arrVal['dbUser']=dbUser;
	arrVal['dbPwd']=dbPwd;
	arrVal['bf_home_dir']=bf_home_dir;
	arrVal['old_files_age']=old_files_age;
	arrVal['rsync_to']=rsync_to;
	$('#listStoredDB option').each(function(){ 
		DBs.push( $(this).text() );
	});
	arrVal['dbList']=DBs;
	$('#listFolders option').each(function(){ 
		bFolders.push( $(this).text() );
	});
	arrVal['foldersList']=bFolders;
	return arrVal;
}

</script>
<body>

<div id=mainPane style='width:98%;float:left;'>
<span style='font-size:large;'>
Server name:  <a href='?' title=RESET><?=gethostname()?></a>
</span>
<input type=button id='xmlLoad' onClick='xml_load()' value='Load cfg'> 
<input type=button id='xmlSave' onClick='xml_save()' value='Save cfg'> 
<!-- textarea style='width:100%' id=texta name=texta rows=30></textarea -->
<input type=button id='createScript' onClick='script_create()' value='Create Script'> </p>
<div style="clear:both"></div>



<div style='float:left;width:45%;'>
<fieldset>
<legend style='border:1px  #50a0a0 solid;background-color:#ccccff'>Databases</legend>
<p>User: <input type=text name=dbUser id=dbUser size=10 value=<?=$dbUser?>>
Password: <input type=text name=dbPwd id=dbPwd size=10value=<?=$dbPwd?>>
<input type=button id='chkConBtn' onClick='check_conn()' value='Check Connection'> </p>
<div style='float:left;'>
	<p>Currently defined databases:</p>
	<select multiple=multiple size=12 name=listDB id=listDB ondblclick='db_add()'></select>
</div>
<div style='float:left;'>
	<br /> <br /> <br /> <br /> <br /> <br /> <br /> <br /> <br />
	<p>
	<input type=button id='addDbToList' onClick='db_add()' value='Add ->'>
	</p> <p>
	<input type=button id='rmDbFroList' onClick='db_rm()' value='<- Del'>
	</p>
</div>
<div style='float:left;'>
	<p>Databases being stored:</p>
	<select multiple=multiple size=12 name='listStoredDB[]' id=listStoredDB ondblclick='db_remove()'></select>
</div>
</fieldset>
</div>




<div style='float:left;width:45%;'>
<div id=divFolders style='float:left;'>
<fieldset>
<legend>Folders</legend>
<p>This folder <input type=text name=addFolder id=addFolder>
<input type=button id='addFolderButton' onClick='folder_add()' value='Add'> </p>
<p>Files/folders being stored:</p>
<select multiple=multiple size=13 name=listFolders[] id=listFolders></select><input type=button id='delFolderButton' onClick='folder_rm()' value='Delete
selected'>
</p>
<p></p>
</fieldset>
</div>
</div>

<div style="clear:both"></div>

<div id=otherParameters style='float:left;'>
<fieldset>
<legend>Other parameters</legend>
<ul>
<li>Where to store backup files:
<input type=text name=bf_home_dir id=bf_home_dir></li>
<li>Old files max age:
<input type=text name=old_files_age id=old_files_age></li>
<li>rsync to:
<input type=text name=rsync_to id=rsync_to></li>
</ul>
</fieldset>
</div>





</div>
<div style="clear:both"></div>

</body>
</html>
