<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path('edit.php'));
loggedinorreturn();
parked();
if ($CURUSER["uploadpos"] == 'no')
	stderr($lang_upload['std_sorry'], $lang_upload['std_unauthorized_to_upload'],false);

$standaloneForumId = 0;
if (array_key_exists('forumid', $_GET)) {
    $forumId = filter_var($_GET['forumid'], FILTER_VALIDATE_INT);
    if ($forumId === false || $forumId < 1) {
        permissiondenied();
    }
    \Nexus\Database\RuntimeMigrations::ensureStandaloneForumTables();
    $forumResult = sql_query(
        "SELECT minclassread, minclasswrite, minclasscreate FROM standalone_forums WHERE id = "
        . (int) $forumId . " LIMIT 1"
    ) or sqlerr(__FILE__, __LINE__);
    $uploadForum = mysql_fetch_assoc($forumResult);
    $userClass = get_user_class();
    if (
        !$uploadForum
        || $userClass < $uploadForum['minclassread']
        || $userClass < $uploadForum['minclasswrite']
        || $userClass < $uploadForum['minclasscreate']
    ) {
        permissiondenied();
    }
    $standaloneForumId = (int) $forumId;
}

if ($enableoffer == 'yes')
    $has_allowed_offer = get_row_count("offers","WHERE allowed='allowed' AND userid = ". sqlesc($CURUSER["id"]));
else $has_allowed_offer = 0;
$uploadfreely = user_can_upload("torrents");
$allowtorrents = ($has_allowed_offer || $uploadfreely);
$allowspecial = user_can_upload("music");

if (!$allowtorrents && !$allowspecial)
	stderr($lang_upload['std_sorry'],$lang_upload['std_please_offer'],false);
$allowtwosec = ($allowtorrents && $allowspecial);

$brsectiontype = $browsecatmode;
$spsectiontype = $specialcatmode;
/*
$showsource = (($allowtorrents && get_searchbox_value($brsectiontype, 'showsource')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showsource'))); //whether show sources or not
$showmedium = (($allowtorrents && get_searchbox_value($brsectiontype, 'showmedium')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showmedium'))); //whether show media or not
$showcodec = (($allowtorrents && get_searchbox_value($brsectiontype, 'showcodec')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showcodec'))); //whether show codecs or not
$showstandard = (($allowtorrents && get_searchbox_value($brsectiontype, 'showstandard')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showstandard'))); //whether show standards or not
$showprocessing = (($allowtorrents && get_searchbox_value($brsectiontype, 'showprocessing')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showprocessing'))); //whether show processings or not
$showteam = (($allowtorrents && get_searchbox_value($brsectiontype, 'showteam')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showteam'))); //whether show teams or not
$showaudiocodec = (($allowtorrents && get_searchbox_value($brsectiontype, 'showaudiocodec')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showaudiocodec'))); //whether show languages or not
*/
$settingMain = get_setting('main');
$torrentRep = new \App\Repositories\TorrentRepository();
$searchBoxRep = new \App\Repositories\SearchBoxRepository();
$tagRep = new \App\Repositories\TagRepository();
$chdModeId = \App\Models\SearchBox::query()->where('name', 'chd')->value('id');
$uploadModes = [];
if ($allowtorrents) {
    $uploadModes[] = (int) $browsecatmode;
}
if ($allowspecial) {
    $uploadModes[] = (int) $specialcatmode;
}
$qualityDatalists = [];
$tagDatalists = [];
foreach (array_unique($uploadModes) as $uploadMode) {
    if ($uploadMode !== 0 && ($chdModeId === null || $uploadMode !== (int) $chdModeId)) {
        continue;
    }

    $qualityOptions = \App\Models\TorrentExtra::query()
        ->whereNotNull('quality')
        ->where('quality', '!=', '')
        ->whereHas('torrent.basic_category', fn ($query) => $query->where('mode', $uploadMode))
        ->distinct()
        ->orderBy('quality')
        ->pluck('quality');
    $tagQuery = \App\Models\Tag::query()
        ->where('mode', $uploadMode)
        ->orderBy('name');
    if (!user_can('torrent-set-special-tag')) {
        $tagQuery->whereNotIn('id', \App\Models\Tag::listSpecial());
    }
    $tagOptions = $tagQuery->pluck('name');

    $qualityDatalists[$uploadMode] = '';
    foreach ($qualityOptions as $qualityOption) {
        $qualityDatalists[$uploadMode] .= '<option value="' . htmlspecialchars($qualityOption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></option>';
    }
    $tagDatalists[$uploadMode] = '';
    foreach ($tagOptions as $tagOption) {
        $tagDatalists[$uploadMode] .= '<option value="' . htmlspecialchars($tagOption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></option>';
    }
}
stdhead($lang_upload['head_upload']);
?>
	<form id="compose" enctype="multipart/form-data" action="takeupload.php" method="post" name="upload">
            <?php if ($standaloneForumId > 0) { ?>
            <input type="hidden" name="standalone_forum_id" value="<?php echo $standaloneForumId ?>">
            <?php } ?>
			<?php
			print("<p align=\"center\">".$lang_upload['text_red_star_required']."</p>");
			?>
			<table border="1" cellspacing="0" cellpadding="5" width="97%">
				<tr>
					<td class='colhead' colspan='2' align='center'>
						<?php echo $lang_upload['text_tracker_url'] ?>: &nbsp;&nbsp;&nbsp;&nbsp;<b><?php echo  get_tracker_schema_and_host($CURUSER['tracker_url_id'], true)?></b>
						<?php
						if(!is_writable(getFullDirectory($torrent_dir)))
						print("<br /><br /><b>ATTENTION</b>: Torrent directory isn't writable. Please contact the administrator about this problem!");
						if(!$max_torrent_size)
						print("<br /><br /><b>ATTENTION</b>: Max. Torrent Size not set. Please contact the administrator about this problem!");
						?>
					</td>
				</tr>
				<?php
				tr($lang_upload['row_torrent_file']."<font color=\"red\">*</font>", "<input type=\"file\" class=\"file\" id=\"torrent\" name=\"file\" onchange=\"getname()\" />\n", 1);
				if ($altname_main == 'yes'){
					tr($lang_upload['row_torrent_name'], "<b>".$lang_upload['text_english_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px;\" name=\"name\" />&nbsp;&nbsp;&nbsp;
<b>".$lang_upload['text_chinese_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px\" name=\"cnname\"><br /><font class=\"medium\">".$lang_upload['text_titles_note']."</font>", 1);
				} else {
				    $autoFillText = $lang_upload['fill_quality'];
				    $nameInput = $torrentRep->buildUploadFieldInput("name", "", $lang_upload['text_torrent_name_note'], $autoFillText);
                    tr($lang_upload['row_torrent_name'], $nameInput, 1);
                }
                tr(
                    $lang_upload['row_synonyms_name'] ?? 'Synonyms Name',
                    '<input type="text" name="synonyms_name" style="width: 99%;" />',
                    1
                );

				if ($smalldescription_main == 'yes')
				tr($lang_upload['row_small_description'], "<input type=\"text\" style=\"width: 99%;\" name=\"small_descr\" /><br /><font class=\"medium\">".$lang_upload['text_small_description_note']."</font>", 1);
				get_external_tr("", true, false);
				if ($settingMain['enable_pt_gen_system'] == 'yes') {
                    $ptGen = new \Nexus\PTGen\PTGen();
                    echo $ptGen->renderUploadPageFormInput("");
                }
                tr(
                    $lang_upload['row_myanimelist_link'],
                    '<input type="url" name="myanimelist_link" style="width: 99%;" />',
                    1
                );
                tr(
                    'Poster',
                    '<label><input type="radio" name="poster_source" value="link" checked> Provide Image Link</label> '
                    . '<label><input type="radio" name="poster_source" value="file"> Upload Image File</label><br>'
                    . '<input type="url" name="poster_url" id="poster_url" style="width: 99%;" placeholder="For your own poster use direct link to the image; leave empty for scraper imported image">'
                    . '<input type="file" name="poster_file" id="poster_file" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">',
                    1
                );
				if ($enablenfo_main=='yes') {
                    tr($lang_upload['row_nfo_file'], "<input type=\"file\" class=\"file\" name=\"nfo\" /><br /><font class=\"medium\">".$lang_upload['text_only_viewed_by'].get_user_class_name($viewnfo_class,false,true,true).$lang_upload['text_or_above']."</font>", 1);
                }
                //price
                if (user_can('torrent-set-price') && get_setting("torrent.paid_torrent_enabled") == "yes") {
                    $maxPrice = get_setting("torrent.max_price");
                    $pricePlaceholder = "";
                    if ($maxPrice > 0) {
                        $pricePlaceholder = nexus_trans("label.torrent.max_price_help", ["max_price" => $maxPrice]);
                    }
                    tr(nexus_trans('label.torrent.price'), '<input type="number" min="0" name="price" placeholder="'.$pricePlaceholder.'" />&nbsp;&nbsp;' . nexus_trans('label.torrent.price_help', ['tax_factor' => (floatval(get_setting('torrent.tax_factor', 0)) * 100) . '%']), 1);
                }

				print("<tr><td class=\"rowhead\" style='padding: 3px' valign=\"top\">Content Info<font color=\"red\">*</font></td><td class=\"rowfollow\">");
				$descriptionTemplate = "[b]Content Info[/b]\n"
				    . "Main:\n"
				    . "English:\n"
				    . "Japanese:\n"
				    . "Type:\n"
				    . "Aired:\n"
				    . "Duration:\n"
				    . "Episodes:\n"
				    . "Rating: of 10\n"
				    . "Studios:\n"
				    . "Genres:\n\n"
				    . "[b]Synopsis[/b]\n\n\n"
				    . "[b]Screenshots[/b]\n";
				textbbcode("upload","descr", $descriptionTemplate, false, 130, true);
				print("</td></tr>\n");

                if ($settingMain['enable_technical_info'] == 'yes') {
                    tr($lang_functions['text_technical_info'], '<textarea name="technical_info" rows="8" style="width: 99%;"></textarea><br/>' . $lang_functions['text_technical_info_help_text'], 1);
                }

				if ($allowtorrents){
					$disablespecial = " onchange=\"disableother('browsecat','specialcat')\"";
					$s = "<select name=\"type\" id=\"browsecat\" data-mode='$browsecatmode' ".($allowtwosec ? $disablespecial : "").">\n<option value=\"0\">".$lang_upload['select_choose_one']."</option>\n";
					$cats = \App\Models\Category::listUploadOptions((int) $browsecatmode);
					foreach ($cats as $row)
                        $s .= "<option value=\"" . (int) $row->id . "\">" . htmlspecialchars(($row->parent ? $row->parent->name . ' / ' : '') . $row->name, ENT_QUOTES, 'UTF-8') . "</option>\n";
					$s .= "</select>\n";
				}
				else $s = "";
				if ($allowspecial){
					$disablebrowse = " onchange=\"disableother('specialcat','browsecat')\"";
					$s2 = "<select name=\"type\" id=\"specialcat\" data-mode='$specialcatmode' ".$disablebrowse.">\n<option value=\"0\">".$lang_upload['select_choose_one']."</option>\n";
					$cats2 = \App\Models\Category::listUploadOptions((int) $specialcatmode);
					foreach ($cats2 as $row)
                        $s2 .= "<option value=\"" . (int) $row->id . "\">" . htmlspecialchars(($row->parent ? $row->parent->name . ' / ' : '') . $row->name, ENT_QUOTES, 'UTF-8') . "</option>\n";
					$s2 .= "</select>\n";
				}
				else $s2 = "";
				$typeLabel = (int) $browsecatmode === 0 || (int) $specialcatmode === 0
                    ? \App\Models\Category::getLabelName()
                    : $lang_upload['row_type'];
				tr($typeLabel."<font color=\"red\">*</font>", ($allowtwosec ? $lang_upload['text_to_browse_section'] : "").$s.($allowtwosec ? $lang_upload['text_to_special_section'] : "").$s2.($allowtwosec ? $lang_upload['text_type_note'] : ""),1);
/*
				if ($showsource || $showmedium || $showcodec || $showaudiocodec || $showstandard || $showprocessing){
					if ($showsource){
						$source_select = torrent_selection($lang_upload['text_source'],"source_sel","sources");
					}
					else $source_select = "";

					if ($showmedium){
						$medium_select = torrent_selection($lang_upload['text_medium'],"medium_sel","media");
					}
					else $medium_select = "";

					if ($showcodec){
						$codec_select = torrent_selection($lang_upload['text_codec'],"codec_sel","codecs");
					}
					else $codec_select = "";

					if ($showaudiocodec){
						$audiocodec_select = torrent_selection($lang_upload['text_audio_codec'],"audiocodec_sel","audiocodecs");
					}
					else $audiocodec_select = "";

					if ($showstandard){
						$standard_select = torrent_selection($lang_upload['text_standard'],"standard_sel","standards");
					}
					else $standard_select = "";

					if ($showprocessing){
						$processing_select = torrent_selection($lang_upload['text_processing'],"processing_sel","processings");
					}
					else $processing_select = "";

					tr($lang_upload['row_quality'], $source_select . $medium_select. $codec_select . $audiocodec_select. $standard_select . $processing_select, 1 );
				}

				if ($showteam){
					if ($showteam){
						$team_select = torrent_selection($lang_upload['text_team'],"team_sel","teams");
					}
					else $showteam = "";

					tr($lang_upload['row_content'],$team_select,1);
				}
*/
                $customField = new \Nexus\Field\Field();
                $hitAndRunRep = new \App\Repositories\HitAndRunRepository();
                if ($allowtorrents) {
                    if ((int) $browsecatmode === 0) {
                        $qualityInput = '<input type="text" name="quality[0]" list="quality-options-browse" maxlength="255" style="width: 99%;" /><datalist id="quality-options-browse">'.$qualityDatalists[0].'</datalist><br><small class="text-muted">(e.g. Blu-Ray, DVD, UHD etc)</small>';
                        tr($lang_upload['row_quality'], $qualityInput, 1, "mode_$browsecatmode");
                    } else {
                        $selectNormal = $searchBoxRep->renderTaxonomySelect($browsecatmode);
                        if ($chdModeId !== null && (int) $browsecatmode === (int) $chdModeId) {
                            $selectNormal .= '<br><input type="text" name="quality['.$browsecatmode.']" list="quality-options-browse" maxlength="255" style="width: 99%;" /><datalist id="quality-options-browse">'.$qualityDatalists[$chdModeId].'</datalist><br><small class="text-muted">(e.g. Blu-Ray, DVD, UHD etc)</small>';
                        }
                        tr($lang_upload['row_quality'], $selectNormal, 1, "mode_$browsecatmode");
                    }
                    echo $customField->renderOnUploadPage(0, $browsecatmode);
                    echo $hitAndRunRep->renderOnUploadPage('', $browsecatmode);
                    if ((int) $browsecatmode === 0) {
                        $tagInput = '<input type="text" name="tag_name[0]" list="tag-options-browse" maxlength="255" style="width: 99%;" /><datalist id="tag-options-browse">'.$tagDatalists[0].'</datalist><br><small class="text-muted">(e.g. Crime, Horror, Anime etc)</small>';
                        tr($lang_functions['text_tags'], $tagInput, 1, "mode_$browsecatmode");
                    } elseif ($chdModeId !== null && (int) $browsecatmode === (int) $chdModeId) {
                        $tagInput = $tagRep->renderCheckbox($browsecatmode)
                            . '<br><input type="text" name="tag_name['.$browsecatmode.']" list="tag-options-browse" maxlength="255" style="width: 99%;" /><datalist id="tag-options-browse">'.$tagDatalists[$chdModeId].'</datalist>';
                        tr($lang_functions['text_tags'], $tagInput, 1, "mode_$browsecatmode");
                    } else {
                        tr($lang_functions['text_tags'], $tagRep->renderCheckbox($browsecatmode), 1, "mode_$browsecatmode");
                    }
                }
                if ($allowspecial) {
                    if ((int) $specialcatmode === 0) {
                        $qualityInput = '<input type="text" name="quality[0]" list="quality-options-special" maxlength="255" style="width: 99%;" /><datalist id="quality-options-special">'.$qualityDatalists[0].'</datalist><br><small class="text-muted">(e.g. Blu-Ray, DVD, UHD etc)</small>';
                        tr($lang_upload['row_quality'], $qualityInput, 1, "mode_$specialcatmode");
                    } else {
                        $selectNormal = $searchBoxRep->renderTaxonomySelect($specialcatmode);
                        if ($chdModeId !== null && (int) $specialcatmode === (int) $chdModeId) {
                            $selectNormal .= '<br><input type="text" name="quality['.$specialcatmode.']" list="quality-options-special" maxlength="255" style="width: 99%;" /><datalist id="quality-options-special">'.$qualityDatalists[$chdModeId].'</datalist><br><small class="text-muted">(e.g. Blu-Ray, DVD, UHD etc)</small>';
                        }
                        tr($lang_upload['row_quality'], $selectNormal, 1, "mode_$specialcatmode");
                    }
                    echo $customField->renderOnUploadPage(0, $specialcatmode);
                    echo $hitAndRunRep->renderOnUploadPage('', $specialcatmode);
                    if ((int) $specialcatmode === 0) {
                        $tagInput = '<input type="text" name="tag_name[0]" list="tag-options-special" maxlength="255" style="width: 99%;" /><datalist id="tag-options-special">'.$tagDatalists[0].'</datalist><br><small class="text-muted">(e.g. Crime, Horror, Anime etc)</small>';
                        tr($lang_functions['text_tags'], $tagInput, 1, "mode_$specialcatmode");
                    } elseif ($chdModeId !== null && (int) $specialcatmode === (int) $chdModeId) {
                        $tagInput = $tagRep->renderCheckbox($specialcatmode)
                            . '<br><input type="text" name="tag_name['.$specialcatmode.']" list="tag-options-special" maxlength="255" style="width: 99%;" /><datalist id="tag-options-special">'.$tagDatalists[$chdModeId].'</datalist>';
                        tr($lang_functions['text_tags'], $tagInput, 1, "mode_$specialcatmode");
                    } else {
                        tr($lang_functions['text_tags'], $tagRep->renderCheckbox($specialcatmode), 1, "mode_$specialcatmode");
                    }
                }

				//==== offer dropdown for offer mod  from code by S4NE
				$offerres = sql_query("SELECT id, name FROM offers WHERE userid = ".sqlesc($CURUSER['id'])." AND allowed = 'allowed' ORDER BY name ASC") or sqlerr(__FILE__, __LINE__);
				if (mysql_num_rows($offerres) > 0)
				{
					$offer = "<select name=\"offer\"><option value=\"0\">".$lang_upload['select_choose_one']."</option>";
					while($offerrow = mysql_fetch_array($offerres))
						$offer .= "<option value=\"" . $offerrow["id"] . "\">" . htmlspecialchars($offerrow["name"]) . "</option>";
					$offer .= "</select>";
					tr($lang_upload['row_your_offer']. (!$uploadfreely && !$allowspecial ? "<font color=red>*</font>" : ""), $offer.$lang_upload['text_please_select_offer'] , 1);
					$getOfferJs = <<<JS
jQuery('select[name="offer"]').on("change", function () {
    let id = this.value
    if (id == 0) {
        return
    }
    let params = {action: "getOffer", params: {id: id}}
    jQuery.post("ajax.php", params, function (response) {
        console.log(response)
        if (response.ret != 0) {
            alert(response.msg)
            return
        }
        jQuery("#name").val(response.data.name)
        clearContent()
        doInsert(response.data.descr, '', false)
        jQuery("#specialcat").prop('disabled', false).val(0)
        jQuery("#browsecat").prop('disabled', false).val(response.data.category)
    }, 'json')
})
JS;
					\Nexus\Nexus::js($getOfferJs, 'footer', false);

				}
				//===end

                //pick
                $pickcontent = '';
                if(user_can('torrentonpromotion'))
                {
                    $pickcontent .= "<b>".$lang_edit['row_special_torrent'].":&nbsp;</b><select name=\"sel_spstate\" style=\"width: 100px;\"><option value=\"0\" selected=\"selected\">".$lang_edit['select_use_global_setting']."</option>".promotion_selection(0, 0)."</select>&nbsp;&nbsp;&nbsp;";
                    $pickcontent .= '<select name="promotion_time_type" onchange="if (this.value == \'2\') {document.getElementById(\'promotion_until_note\').style.display = \'\';} else {document.getElementById(\'promotion_until_note\').style.display = \'none\';}"><option value="0" selected="selected">'.$lang_edit['select_use_global_setting'].'</option><option value="1">'.$lang_edit['select_forever'].'</option><option value="2">'.$lang_edit['select_until'].'</option></select><span id="promotion_until_note" style="display: none;">';
                    $pickcontent .= '<input type="text" id="promotionuntiltime" name="promotionuntil" style="width: 120px;" value="" />';
                    $pickcontent .= '&nbsp;('.$lang_edit['text_ie_for'].'<select name="promotionaddedtime" onchange="document.getElementById(\'promotionuntiltime\').value=this.value;"><option value="">'.$lang_upload['select_choose_one'].'</option>';
                    $promotionStartTime = time();
                    foreach (array(900, 1800, 3600, 5400, 7200, 14400, 21600, 28800, 43200, 64800, 86400, 129600, 259200, 604800, 1296000, 2592000, 7776000, 15552000, 31104000) as $seconds) {
                        $promotionUntil = date("Y-m-d H:i:s", $promotionStartTime + $seconds);
                        $pickcontent .= '<option value="'.$promotionUntil.'">'.mkprettytime($seconds).'</option>';
                    }
                    $pickcontent .= '</select>)&nbsp;'.$lang_edit['text_promotion_until_note'].'</span>&nbsp;&nbsp;';
                }
                if(user_can('torrentsticky'))
                {
                    if ($pickcontent) $pickcontent .= '<br />';
                    $options = [];
                    foreach (\App\Models\Torrent::listPosStates() as $key => $value) {
                        $options[] = "<option" . (($row["pos_state"] == $key) ? " selected=\"selected\"" : "" ) . " value=\"" . $key . "\">".$value['text']."</option>";
                    }
                    $pickcontent .= "<b>".$lang_edit['row_torrent_position'].":&nbsp;</b>"."<select name=\"pos_state\" style=\"width: 100px;\">" . implode('', $options) . "</select>&nbsp;&nbsp;&nbsp;";
                    $pickcontent .= datetimepicker_input('pos_state_until', '', nexus_trans('label.deadline') . ":&nbsp;", ['require_files' => true]);
                }
                if(user_can('torrentmanage') && ($CURUSER["picker"] == 'yes' || get_user_class() >= \App\Models\User::CLASS_SYSOP))
                {
                    if ($pickcontent) $pickcontent .= '<br />';
                    $pickcontent .= "<b>".$lang_edit['row_recommended_movie'].":&nbsp;</b>"."<select name=\"picktype\" style=\"width: 100px;\">";
                    foreach (\App\Models\Torrent::listPickInfo(true) as $_pick_type => $_pick_type_text) {
                        $pickcontent .= sprintf('<option value="%s">%s</option>', $_pick_type, $_pick_type_text);
                    }
                    $pickcontent .= '</select>';
                }
                if ($pickcontent) {
                    tr($lang_edit['row_pick'], $pickcontent, 1);
                }

				if(user_can('beanonymous'))
				{
					tr($lang_upload['row_show_uploader'], "<input type=\"checkbox\" name=\"uplver\" value=\"yes\" />".$lang_upload['checkbox_hide_uploader_note'], 1);
				}
				?>
				<tr><td class="toolbox" align="center" colspan="2"><b><?php echo $lang_upload['text_read_rules']?></b> <input id="qr" type="submit" class="btn" value="<?php echo $lang_upload['submit_upload']?>" /></td></tr>
		</table>
	</form>
<?php
\Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
\Nexus\Nexus::js('js/ptgen.js', 'footer', true);
$customFieldJs = <<<JS
jQuery("#compose").on("change", "select[name=type]", function () {
    let _this = jQuery(this);
    let mode = _this.attr("data-mode");
    let value = _this.val();
    console.log(mode)
    jQuery("tr[relation]").hide();
    if (value > 0) {
        jQuery("tr[relation=mode_" + mode +"]").show();
    }
})
jQuery("tr[relation]").hide();
JS;
\Nexus\Nexus::js($customFieldJs, 'footer', false);
$posterInputJs = <<<JS
jQuery("#compose").on("change", "input[name=poster_source]", function () {
    const useFile = jQuery("input[name=poster_source]:checked").val() === "file";
    jQuery("#poster_url").toggle(!useFile).prop("disabled", useFile);
    jQuery("#poster_file").toggle(useFile).prop("disabled", !useFile);
})
JS;
\Nexus\Nexus::js($posterInputJs, 'footer', false);
stdfoot();
