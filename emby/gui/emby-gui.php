<?php
/*
	emby-gui.php

	WebGUI wrapper for the NAS4Free/XigmaNAS "Emby Media Server*" add-on created by JoseMR.
	(https://www.xigmanas.com/forums/viewtopic.php?f=71&t=11184)
	*Emby(c) (Emby Media Server) is a registered trademark of Emby(c), Inc.

	Copyright (c) 2016 Andreas Schmidhuber
	All rights reserved.

	Portions of NAS4Free (http://www.nas4free.org).
	Copyright (c) 2012-2016 The NAS4Free Project <info@nas4free.org>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice, this
	   list of conditions and the following disclaimer.
	2. Redistributions in binary form must reproduce the above copyright notice,
	   this list of conditions and the following disclaimer in the documentation
	   and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
	ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

	The views and conclusions contained in the software and documentation are those
	of the authors and should not be interpreted as representing official policies,
	either expressed or implied, of the NAS4Free Project.
*/
require("auth.inc");
require("guiconfig.inc");

$application = "Emby Media Server";
$pgtitle = array(gtext("Extensions"), "Emby Media Server");

// For NAS4Free 10.x versions.
$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
if ($return_val == 0) {
	if (is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
		for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) { if (preg_match('/embyinit/', $config['rc']['postinit']['cmd'][$i])) break; ++$i; }
	}
}

// Initialize some variables.
//$rootfolder = dirname($config['rc']['postinit']['cmd'][$i]);
$pidfile = "/var/run/emby/emby.pid";
$confdir = "/var/etc/embyconf";
$cwdir = exec("/usr/bin/grep 'INSTALL_DIR=' {$confdir}/conf/emby_config | cut -d'\"' -f2");
$rootfolder = $cwdir;
$configfile = "{$rootfolder}/conf/emby_config";
$versionfile = "{$rootfolder}/version";
$date = strftime('%c');
$logfile = "{$rootfolder}/log/emby_ext.log";
$logevent = "{$rootfolder}/log/emby_last_event.log";

// Set the installed Emby package name.
$return_val = mwexec("/usr/bin/grep 'PLEX_CHANNEL=\"embypass\"' {$confdir}/conf/emby_config", true);
if ($return_val == 0) {
	$prdname = "embymediaserver-embypass";
}
else {
	$prdname = "embymediaserver";
}

if ($rootfolder == "") $input_errors[] = gtext("Extension installed with fault");
else {
// Initialize locales.
	$textdomain = "/usr/local/share/locale";
	$textdomain_emby = "/usr/local/share/locale-emby";
	if (!is_link($textdomain_emby)) { mwexec("ln -s {$rootfolder}/locale-emby {$textdomain_emby}", true); }
	bindtextdomain("nas4free", $textdomain_emby);
}
if (is_file("{$rootfolder}/postinit")) unlink("{$rootfolder}/postinit");

// Set default backup directory.
if (1 == mwexec("/bin/cat {$configfile} | /usr/bin/grep 'BACKUP_DIR='")) {
	if (is_file("{$configfile}")) exec("/usr/sbin/sysrc -f {$configfile} BACKUP_DIR={$rootfolder}/backup");
}
$backup_path = exec("/bin/cat {$configfile} | /usr/bin/grep 'BACKUP_DIR=' | cut -d'\"' -f2");

// Retrieve IP@.
$ipaddr = get_ipaddr($config['interfaces']['lan']['if']);
$url = htmlspecialchars("http://{$ipaddr}:32400/web");
$ipurl = "<a href='{$url}' target='_blank'>{$url}</a>";

if ($_POST) {
	if (isset($_POST['start']) && $_POST['start']) {
		$return_val = mwexec("{$rootfolder}/embyinit -s", true);
		if ($return_val == 0) {
			$savemsg .= gtext("Emby Media Server started successfully.");
			exec("echo '{$date}: {$application} successfully started' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("Emby Media Server startup failed.");
			exec("echo '{$date}: {$application} startup failed' >> {$logfile}");
		}
	}

	if (isset($_POST['stop']) && $_POST['stop']) {
		$return_val = mwexec("{$rootfolder}/embyinit -p", true);
		if ($return_val == 0) {
			$savemsg .= gtext("Emby Media Server stopped successfully.");
			exec("echo '{$date}: {$application} successfully stopped' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("Emby Media Server stop failed.");
			exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
		}
	}

	if (isset($_POST['restart']) && $_POST['restart']) {
		$return_val = mwexec("{$rootfolder}/embyinit -r", true);
		if ($return_val == 0) {
			$savemsg .= gtext("Emby Media Server restarted successfully.");
			exec("echo '{$date}: {$application} successfully restarted' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("Emby Media Server restart failed.");
			exec("echo '{$date}: {$application} restart failed' >> {$logfile}");
		}
	}

	if(isset($_POST['upgrade']) && $_POST['upgrade']):
		$cmd = sprintf('%1$s/embyinit -u > %2$s',$rootfolder,$logevent);
		$return_val = 0;
		$output = [];
		exec($cmd,$output,$return_val);
		if($return_val == 0):
			ob_start();
			include("{$logevent}");
			$ausgabe = ob_get_contents();
			ob_end_clean(); 
			$savemsg .= str_replace("\n", "<br />", $ausgabe)."<br />";
		else:
			$input_errors[] = gtext('An error has occurred during upgrade process.');
			$cmd = sprintf('echo %s: %s An error has occurred during upgrade process. >> %s',$date,$application,$logfile);
			exec($cmd);
		endif;
	endif;

	if (isset($_POST['backup']) && $_POST['backup']) {
		$return_val = mwexec("mkdir -p {$backup_path} && cd {$rootfolder} && tar -cf embydata-`date +%Y-%m-%d-%H%M%S`.tar embydata && mv embydata-*.tar {$backup_path}", true);
		if ($return_val == 0) {
			$savemsg .= gtext("Embydata backup created successfully in {$backup_path}.");
			exec("echo '{$date}: Embydata backup successfully created' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("Embydata backup failed.");
			exec("echo '{$date}: Embydata backup failed' >> {$logfile}");
		}
	}

	if (isset($_POST['remove']) && $_POST['remove']) {
		bindtextdomain("nas4free", $textdomain);
		if (is_link($textdomain_emby)) mwexec("rm -f {$textdomain_emby}", true);
		if (is_dir($confdir)) mwexec("rm -Rf {$confdir}", true);
		mwexec("rm /usr/local/www/emby-gui.php && rm -R /usr/local/www/ext/emby-gui", true);
		mwexec("{$rootfolder}/embyinit -t", true);
		exec("echo '{$date}: Extension GUI successfully removed' >> {$logfile}");
		header("Location:index.php");
	}

	// Remove only extension related files during cleanup.
	if (isset($_POST['uninstall']) && $_POST['uninstall']) {
		bindtextdomain("nas4free", $textdomain);
		if (is_link($textdomain_emby)) mwexec("rm -f {$textdomain_emby}", true);
		if (is_dir($confdir)) mwexec("rm -Rf {$confdir}", true);
		mwexec("rm /usr/local/www/emby-gui.php && rm -R /usr/local/www/ext/emby-gui", true);
		mwexec("{$rootfolder}/embyinit -t", true);
		mwexec("{$rootfolder}/embyinit -p && rm -f {$pidfile}", true);
		mwexec("pkg delete -y {$prdname}", true);
		if (isset($_POST['embydata'])) { $uninstall_cmd = "rm -Rf '{$rootfolder}/backup' '{$rootfolder}/conf' '{$rootfolder}/gui' '{$rootfolder}/locale-emby' '{$rootfolder}/log' '{$rootfolder}/embydata' '{$rootfolder}/system' '{$rootfolder}/embyinit' '{$rootfolder}/README' '{$rootfolder}/release_notes' '{$rootfolder}/version'"; }
		else { $uninstall_cmd = "rm -Rf '{$rootfolder}/backup' '{$rootfolder}/conf' '{$rootfolder}/gui' '{$rootfolder}/locale-emby' '{$rootfolder}/log' '{$rootfolder}/system' '{$rootfolder}/embyinit' '{$rootfolder}/README' '{$rootfolder}/release_notes' '{$rootfolder}/version'"; }
		mwexec($uninstall_cmd, true);
		if (is_link("/usr/local/share/{$prdname}")) mwexec("rm /usr/local/share/{$prdname}", true);
		if (is_link("/var/cache/pkg")) mwexec("rm /var/cache/pkg", true);
		if (is_link("/var/db/pkg")) mwexec("rm /var/db/pkg && mkdir /var/db/pkg", true);
		
		// Remove postinit cmd in NAS4Free 10.x versions.
		$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
			if ($return_val == 0) {
				if (is_array($config['rc']['postinit']) && is_array($config['rc']['postinit']['cmd'])) {
					for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) {
					if (preg_match('/embyinit/', $config['rc']['postinit']['cmd'][$i])) { unset($config['rc']['postinit']['cmd'][$i]); }
					++$i;
				}
			}
			write_config();
		}

		// Remove postinit cmd in NAS4Free later versions.
		if (is_array($config['rc']) && is_array($config['rc']['param'])) {
			$postinit_cmd = "{$rootfolder}/embyinit";
			$value = $postinit_cmd;
			$sphere_array = &$config['rc']['param'];
			$updateconfigfile = false;
		if (false !== ($index = array_search_ex($value, $sphere_array, 'value'))) {
			unset($sphere_array[$index]);
			$updateconfigfile = true;
		}
		if ($updateconfigfile) {
			write_config();
			$updateconfigfile = false;
		}
	}
	header("Location:index.php");
}

	if (isset($_POST['save']) && $_POST['save']) {
		// Ensure to have NO whitespace & trailing slash.
		$backup_path = rtrim(trim($_POST['backup_path']),'/');
		if ("{$backup_path}" == "") $backup_path = "{$rootfolder}/backup";
			else exec("/usr/sbin/sysrc -f {$configfile} BACKUP_DIR={$backup_path}");
		if (isset($_POST['enable'])) { 
			exec("/usr/sbin/sysrc -f {$configfile} PLEX_ENABLE=YES");
			mwexec("{$rootfolder}/embyinit", true);
			exec("echo '{$date}: Extension settings saved and enabled' >> {$logfile}");
		}
		else {
			exec("/usr/sbin/sysrc -f {$configfile} PLEX_ENABLE=NO");
			$return_val = mwexec("{$rootfolder}/embyinit -p && rm -f {$pidfile}", true);
			if ($return_val == 0) {
				$savemsg .= gtext("Emby Media Server stopped successfully.");
				exec("echo '{$date}: Extension settings saved and disabled' >> {$logfile}");
			}
			else {
				$input_errors[] = gtext("Emby Media Server stop failed.");
				exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
			}
		}
	}
}

// Update some variables.
$embyenable = exec("/bin/cat {$configfile} | /usr/bin/grep 'PLEX_ENABLE=' | cut -d'\"' -f2");
$backup_path = exec("/bin/cat {$configfile} | /usr/bin/grep 'BACKUP_DIR=' | cut -d'\"' -f2");

function get_version_emby() {
	global $prdname;
	exec("/usr/local/sbin/pkg info -I {$prdname}", $result);
	return ($result[0]);
}

function get_version_ext() {
	global $versionfile;
	exec("/bin/cat {$versionfile}", $result);
	return ($result[0]);
}

function get_process_info() {
	global $pidfile;
	if (exec("/bin/ps acx | /usr/bin/grep -f {$pidfile}")) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gtext("running").'</b>&nbsp;&nbsp;</a>'; }
	else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gtext("stopped").'</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

function get_process_pid() {
	global $pidfile;
	exec("/bin/cat {$pidfile}", $state); 
	return ($state[0]);
}

if (is_ajax()) {
	$getinfo['info'] = get_process_info();
	$getinfo['pid'] = get_process_pid();
	$getinfo['emby'] = get_version_emby();
	$getinfo['ext'] = get_version_ext();
	render_ajax($getinfo);
}

bindtextdomain("nas4free", $textdomain);
include("fbegin.inc");
bindtextdomain("nas4free", $textdomain_emby);
?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'emby-gui.php', null, function(data) {
		$('#getinfo').html(data.info);
		$('#getinfo_pid').html(data.pid);
		$('#getinfo_emby').html(data.emby);
		$('#getinfo_ext').html(data.ext);
	});
});
//]]>
</script>
<!-- The Spinner Elements -->
<script src="js/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis = !(document.iform.enable.checked || enable_change);
	document.iform.start.disabled = endis;
	document.iform.stop.disabled = endis;
	document.iform.restart.disabled = endis;
	document.iform.upgrade.disabled = endis;
	document.iform.backup.disabled = endis;
	document.iform.backup_path.disabled = endis;
	document.iform.backup_pathbrowsebtn.disabled = endis;
}
//-->
</script>
<form action="emby-gui.php" method="post" name="iform" id="iform" onsubmit="spinner()">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td class="tabcont">
			<?php if (!empty($input_errors)) print_input_errors($input_errors);?>
			<?php if (!empty($savemsg)) print_info_box($savemsg);?>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_titleline_checkbox("enable", gtext("Emby"), $embyenable == "YES", gtext("Enable"));?>
				<?php html_text("installation_directory", gtext("Installation directory"), sprintf(gtext("The extension is installed in %s"), $rootfolder));?>
				<tr>
					<td class="vncellt"><?=gtext("Emby version");?></td>
					<td class="vtable"><span name="getinfo_emby" id="getinfo_emby"><?=get_version_emby()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtext("Extension version");?></td>
					<td class="vtable"><span name="getinfo_ext" id="getinfo_ext"><?=get_version_ext()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtext("Status");?></td>
					<td class="vtable"><span name="getinfo" id="getinfo"><?=get_process_info()?></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PID:&nbsp;<span name="getinfo_pid" id="getinfo_pid"><?=get_process_pid()?></span></td>
				</tr>
				<?php html_filechooser("backup_path", gtext("Backup directory"), $backup_path, gtext("Directory to store archive.tar files of the embydata folder."), $backup_path, true, 60);?>
				<?php html_text("url", gtext("WebGUI")." ".gtext("URL"), $ipurl);?>
			</table>
			<div id="submit">
				<input id="save" name="save" type="submit" class="formbtn" title="<?=gtext("Save settings");?>" value="<?=gtext("Save");?>"/>
				<input name="start" type="submit" class="formbtn" title="<?=gtext("Start Emby Media Server");?>" value="<?=gtext("Start");?>" />
				<input name="stop" type="submit" class="formbtn" title="<?=gtext("Stop Emby Media Server");?>" value="<?=gtext("Stop");?>" />
				<input name="restart" type="submit" class="formbtn" title="<?=gtext("Restart Emby Media Server");?>" value="<?=gtext("Restart");?>" />
				<input name="upgrade" type="submit" class="formbtn" title="<?=gtext("Upgrade Extension and Emby Packages");?>" value="<?=gtext("Upgrade");?>" />
				<input name="backup" type="submit" class="formbtn" title="<?=gtext("Backup Embydata Folder");?>" value="<?=gtext("Backup");?>" />
			</div>
			<div id="remarks">
				<?php html_remark("note", gtext("Note"), sprintf(gtext("Use the %s button to create an archive.tar of the embydata folder."), gtext("Backup")));?>
			</div>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_separator();?>
				<?php html_titleline(gtext("Uninstall"));?>
				<?php html_checkbox("embydata", gtext("Embydata"), false, "<font color='red'>".gtext("Activate to delete user data (metadata and configuration) as well during the uninstall process.")."</font>", sprintf(gtext("If not activated the directory %s remains intact on the server."), "{$rootfolder}/embydata"), false);?>
				<?php html_separator();?>
			</table>
			<div id="submit1">
				<input name="remove" type="submit" class="formbtn" title="<?=gtext("Remove Emby Extension GUI");?>" value="<?=gtext("Remove");?>" onclick="return confirm('<?=gtext("Emby Extension GUI will be removed, ready to proceed?");?>')" />
				<input name="uninstall" type="submit" class="formbtn" title="<?=gtext("Uninstall Extension and Emby Media Server completely");?>" value="<?=gtext("Uninstall");?>" onclick="return confirm('<?=gtext("Emby Extension and Emby packages will be completely removed, ready to proceed?");?>')" />
			</div>
		</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
