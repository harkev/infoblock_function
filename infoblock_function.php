<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
const day=45;
const org=29;
const user=17;

// Проверяет дату созданию плюс 45 дней, можно ли повторно отправить запрос или нет
function add_date($givendate)
{
    $fixdate= date('Y-m-d');
    $dateAt = strtotime('+'.day.' day', $givendate);
    $fixdate=strtotime($fixdate);
    if ($dateAt<$fixdate){
        $b=true;
    }else{
        $b=false;
    }
    return array($b,$dateAt);
}

// Выдает информацию по заданному свойству.
function showStatus($elUser,$elStudent){
    $b = true;
    CModule::IncludeModule("iblock");
    $arSelect = Array();
    $arFilter = Array("IBLOCK_ID"=>79, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array(), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();
        if ($arFields['NAME'] ==$elUser.'-'.$elStudent ){
            $elements = array(
                "ID" =>$arFields['ID'],
                "REQUEST" => $arProps['REQUEST']['VALUE'],
                "STATUS" => $arProps['APPLICATION_REQUEST']['VALUE'],
                "STATUS_ID" => $arProps['APPLICATION_REQUEST']['VALUE_ENUM_ID'],
                "DATE_CREATE_UNIX" => $arFields['DATE_CREATE_UNIX'],
                "TIMESTAMP_X_UNIX" => $arFields['TIMESTAMP_X_UNIX'],
                "COMMENT" => $arProps['COMMENT']['VALUE'],
            );
            $DATA = add_date($elements["TIMESTAMP_X_UNIX"]);
            $elements['IN_PROCESS'] =$DATA[0];
            $elements['DATE_NEXT'] =$DATA[1];
            $b=false;
        }
    }
    if ($b){
        return 'false';
    }
    return $elements;
}
// создаем новый элемент в инфоблок и свойства, проверяем допуск по константам и проверяем есть ли уже такой элемент
// если он есть выдает falseб или возращает массив из showStatus.
function addelement($elUser,$elStudent,$elRequert){
    $borg=true;
    $bstud=true;
    foreach (CUser::GetUserGroup($elUser) as $IDGROUP){
        if($IDGROUP==org){
            $borg=false;
        }
    }
    foreach (CUser::GetUserGroup($elStudent) as $IDGROUP){
        if($IDGROUP==user){
            $bstud=false;
        }
    }
    if (($borg)||($bstud)) return false;
    $swhorezult =showStatus($elUser,$elStudent);
    if ($swhorezult=='false') {
        Global $USER;
        $el = new CIBlockElement;
        $PROP = array();
        $PROP['USER'] = $elUser;
        $PROP['STUDENT'] = $elStudent;
        $PROP['PRINYATO'] = "портфолио";
        $PROP['APPLICATION_SOURCE'] = 395;
        $PROP['REQUEST'] = $elRequert;
        $PROP['APPLICATION_REQUEST'] = 396;
        $PROP['COMMENT'] = "";
        $arLoadProductArray = Array(
            "MODIFIED_BY" => $USER->GetID(), // элемент изменен текущим пользователем
            "IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
            "IBLOCK_ID" => 79,
            "PROPERTY_VALUES" => $PROP,
            "NAME" => $elUser . "-" . $elStudent,
            "ACTIVE" => "Y",            // активен
        );
        if ($PRODUCT_ID = $el->Add($arLoadProductArray))
            return showStatus($elUser,$elStudent);
        else
            return "Error: " . $el->LAST_ERROR;
    }else {
        if ($swhorezult['IN_PROCESS']) {
            $data_create=date('d.m.Y H:i:s');
            $PRODUCT_ID=$swhorezult['ID'];
            global $USER;
            $el = new CIBlockElement;
            $PROP = array();
            $PROP['USER'] = $elUser;
            $PROP['STUDENT'] = $elStudent;
            $PROP['PRINYATO'] = "портфолио";
            $PROP['APPLICATION_SOURCE'] = 395;
            $PROP['REQUEST'] = $elRequert;
            $PROP['APPLICATION_REQUEST'] = "";
            $PROP['COMMENT'] = "";
            $arLoadProductArray = Array(
                "MODIFIED_BY"    => $USER->GetID(), // элемент изменен текущим пользователем
                "IBLOCK_SECTION" => false,          // элемент лежит в корне раздела
                "PROPERTY_VALUES"=> $PROP,
                "NAME"           => $elUser . "-" . $elStudent,
                "ACTIVE"         => "Y",            // активен
                "DATE_CREATE"    => $data_create,// date('Y-m-d H-i-s'),
            );
            $res = $el->Update($PRODUCT_ID, $arLoadProductArray);
            return showStatus($elUser,$elStudent);
        }else{
            return $swhorezult;
        }
    }
}

function updateelement($PRODUCT_ID,$elStatusRequert,$elComment)
{
    CModule::IncludeModule("iblock");
    $arSelect = Array();
    $arFilter = Array("IBLOCK_ID" => 79, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array(), $arSelect);
    $elements = array();
    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();
        if ($arFields['ID'] == $PRODUCT_ID) {
            $elUser = $arProps['USER']['VALUE'];
            $elStudent = $arProps['STUDENT']['VALUE'];
            $elRequert = $arProps['REQUEST']['VALUE'];
            $NAME = $arFields['NAME'];
        }
    }
// Собираем Update фильтр и запрос
    global $USER;
    $el = new CIBlockElement;
    $PROP = array();
    $PROP['USER'] = $elUser;
    $PROP['STUDENT'] = $elStudent;
    $PROP['PRINYATO'] = "портфолио";
    $PROP['APPLICATION_SOURCE'] = 395;
    $PROP['REQUEST'] = $elRequert;
    $PROP['APPLICATION_REQUEST'] = $elStatusRequert;
    $PROP['COMMENT'] = $elComment;
    $arLoadProductArray = Array(
        "MODIFIED_BY" => $USER->GetID(), // элемент изменен текущим пользователем
        "IBLOCK_SECTION" => false,          // элемент лежит в корне раздела
        "PROPERTY_VALUES" => $PROP,
        "NAME" => $NAME,
        "ACTIVE" => "Y",            // активен
    );
    $res = $el->Update($PRODUCT_ID, $arLoadProductArray);
    return $res;
}


// Пример получить поста данные и отредактировать свойства инфоблога
$PRODUCT_ID=$_POST['id'];
$elStatusRequert=397;
if ($_POST['class']=='danger'){
    $elStatusRequert=398;
}
if ($_POST['class']=='work'){
    $elStatusRequert=396;
}
$elComment=$_POST['text'];
updateelement($PRODUCT_ID,$elStatusRequert,$elComment);


?>

