<?
$moduleID = 'iplogic.beru';
define("ADMIN_MODULE_NAME", $moduleID);

$baseFolder = realpath(__DIR__ . "/../../..");

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');

use \Bitrix\Main\Localization\Loc,
	\Iplogic\Beru\ErrorTable as Error,
	\Iplogic\Beru\ProfileTable;

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleID);

$checkParams = [
	"ID" => true,
	"CLASS" => "\Iplogic\Beru\ErrorTable"
];

include($baseFolder."/modules/".$moduleID."/prolog.php");

Loc::loadMessages(__FILE__);

if ($MODULE_ACCESS == "D") {
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	CAdminMessage::ShowMessage(Loc::getMessage("ACCESS_DENIED"));
	require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
	die();
}


if($arFields["STATE"] == "NW") {
	$arF = ["STATE"=>"RD"];
	$result = Error::update($ID, $arF);
	if(!$result->isSuccess())
		$message = new CAdminMessage(Loc::getMessage("IPL_MA_ERROR_STATUC_UPDATE")." (".$result->getErrorMessages().")");
}


$rsProfiles = ProfileTable::getList();
while($arProfile = $rsProfiles->Fetch()){
	$arProfiles[$arProfile["ID"]] = $arProfile["NAME"]." [".$arProfile["ID"]."]";
}


$aTabs = [
	["DIV" => "edit1", "TAB" => Loc::getMessage("IPL_MA_DETAIL"), "ICON"=>"main_user_edit", "TITLE"=>Loc::getMessage("IPL_MA_DETAIL_TITLE")],
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);

$aMenu = [
	[
		"TEXT"  => Loc::getMessage("IPL_MA_LIST"),
		"TITLE" => Loc::getMessage("IPL_MA_LIST_TITLE"),
		"LINK"  => "iplogic_beru_error_list.php?lang=".LANG,
		"ICON"  => "btn_list",
	],
];
if($MODULE_ACCESS >= "W") {
	$aMenu[] = [
		"SEPARATOR" => "Y"
	];
	$aMenu[] = [
		"TEXT"  => Loc::getMessage("IPL_MA_DELETE"),
		"TITLE" => Loc::getMessage("IPL_MA_DELETE_TITLE"),
		"LINK"  => "javascript:deleteConfirm();",
	];
}


if( $request->get("action") == "delete"
	&& $MODULE_ACCESS >= "W"
	&& $fatalErrors == ""
) {
	$result = Error::delete($ID);
	if ($result->isSuccess()) {
		LocalRedirect("/bitrix/admin/iplogic_beru_error_list.php?lang=".LANG);
	}
	else {
		$message = new CAdminMessage(Loc::getMessage("IPL_MA_ERROR_DELETE")." (".$result->getErrorMessages().")");
	}
}

$APPLICATION->SetTitle(Loc::getMessage("IPL_MA_ERROR_DETAIL_TITLE")." #".$ID);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($fatalErrors != ""){
	CAdminMessage::ShowMessage($fatalErrors);
	require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
	die();
}

if($message)
	echo $message->Show();


$context = new CAdminContextMenu($aMenu);
$context->Show();

$tabControl->Begin();
$tabControl->BeginNextTab();

echo Loc::getMessage("IPL_MA_PROFILE").": <a href=\"/bitrix/admin/iplogic_beru_profile_edit.php?ID=".
	 $arFields["PROFILE_ID"]."&lang=".LANGUAGE_ID."\">".$arProfiles[$arFields["PROFILE_ID"]]."</a><br><br>";
echo Loc::getMessage("IPL_MA_TIME").": ".$arFields["HUMAN_TIME"]."<br><br>";
echo Loc::getMessage("IPL_MA_ERROR").": ".$arFields["ERROR"]."<br><br>";
if ($arFields["LOG"] > 0) {
	echo "<a href=\"/bitrix/admin/iplogic_beru_log_detail.php?ID=".
		$arFields["LOG"]."&lang=".LANGUAGE_ID."\">".Loc::getMessage("IPL_MA_LOG")."</a><br><br>";
}
echo Loc::getMessage("IPL_MA_DETAIL").":<br><br>".$arFields["DETAILS"]."<br><br>";

$tabControl->End();

echo ("<script>
	function deleteConfirm() {
		if (window.confirm('".Loc::getMessage("IPL_MA_DELETE_CONFIRM")."')) {
			window.location.href='iplogic_beru_error_detail.php?ID=".$ID."&action=delete&lang=".LANG."';
		}
	}
</script>");


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>