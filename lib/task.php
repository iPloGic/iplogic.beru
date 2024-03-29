<?

namespace Iplogic\Beru;

use \Bitrix\Main,
	\Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Application,
	\Iplogic\Beru\YMAPI,
	\Iplogic\Beru\ProductTable,
	\Iplogic\Beru\ProfileTable,
	\Iplogic\Beru\ApiLogTable;

IncludeModuleLangFile(Application::getDocumentRoot() . BX_ROOT . "/modules/iplogic.beru/lib/lib.php");

/**
 * Class TaskTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> PROFILE_ID int mandatory
 * <li> UNIX_TIMESTAMP int mandatory
 * <li> HUMAN_TIME string(19) mandatory
 * <li> TYPE string(20) optional
 * <li> STATE string(2) mandatory
 * <li> ENTITY_ID string(255) optional
 * <li> TRYING int optional
 * </ul>
 *
 * @package Iplogic\Beru
 **/
class TaskTable extends Main\Entity\DataManager
{

	public static $moduleID = "iplogic.beru";

	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_iplogicberu_task';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return [
			'ID'             => [
				'data_type'    => 'integer',
				'primary'      => true,
				'autocomplete' => true,
				'title'        => Loc::getMessage('TASK_ENTITY_ID_FIELD'),
			],
			'PROFILE_ID'     => [
				'data_type' => 'integer',
				'required'  => true,
				'title'     => Loc::getMessage('TASK_ENTITY_PROFILE_ID_FIELD'),
			],
			'UNIX_TIMESTAMP' => [
				'data_type' => 'integer',
				'required'  => true,
				'title'     => Loc::getMessage('TASK_ENTITY_UNIX_TIMESTAMP_FIELD'),
			],
			'HUMAN_TIME' => array(
				'data_type' => 'string',
				'required' => true,
				'validation' => array(__CLASS__, 'validateHumanTime'),
				'title' => Loc::getMessage('API_LOG_ENTITY_HUMAN_TIME_FIELD'),
			),
			'TYPE'           => [
				'data_type'  => 'string',
				'validation' => [__CLASS__, 'validateType'],
				'title'      => Loc::getMessage('TASK_ENTITY_TYPE_FIELD'),
			],
			'STATE'          => [
				'data_type'  => 'string',
				'required'   => true,
				'validation' => [__CLASS__, 'validateState'],
				'title'      => Loc::getMessage('TASK_ENTITY_STATE_FIELD'),
			],
			'ENTITY_ID'      => [
				'data_type'  => 'string',
				'validation' => [__CLASS__, 'validateEntityId'],
				'title'      => Loc::getMessage('TASK_ENTITY_ENTITY_ID_FIELD'),
			],
			'TRYING'         => [
				'data_type' => 'integer',
				'title'     => Loc::getMessage('TASK_ENTITY_TRYING_FIELD'),
			],
		];
	}

	/**
	 * Returns validators for HUMAN_TIME field.
	 *
	 * @return array
	 */
	public static function validateHumanTime()
	{
		return array(
			new Main\Entity\Validator\Length(null, 19),
		);
	}

	/**
	 * Returns validators for TYPE field.
	 *
	 * @return array
	 */
	public static function validateType()
	{
		return [
			new Main\Entity\Validator\Length(null, 20),
		];
	}

	/**
	 * Returns validators for STATE field.
	 *
	 * @return array
	 */
	public static function validateState()
	{
		return [
			new Main\Entity\Validator\Length(null, 2),
		];
	}

	/**
	 * Returns validators for ENTITY_ID field.
	 *
	 * @return array
	 */
	public static function validateEntityId()
	{
		return [
			new Main\Entity\Validator\Length(null, 255),
		];
	}


	public static function add(array $arFields)
	{
		$arFields["HUMAN_TIME"] = date('d.m.Y H:i:s', $arFields["UNIX_TIMESTAMP"]);
		return parent::add($arFields);
	}


	public static function update($ID, array $arFields)
	{
		if (isset($arFields["UNIX_TIMESTAMP"])) {
			$arFields["HUMAN_TIME"] = date('d.m.Y H:i:s', $arFields["UNIX_TIMESTAMP"]);
		}
		return parent::update($ID, $arFields);
	}


	public static function getById($ID)
	{
		$result = parent::getById($ID);
		return $result->Fetch();
	}


	public static function clear() {
		$conn = Application::getConnection();
		$helper = $conn->getSqlHelper();
		$strSql = "TRUNCATE TABLE ".$helper->quote(self::getTableName());
		$rsData = $conn->query($strSql);
		unset($helper, $conn);
		return true;
	}


	public static function deleteByProfileId($ID)
	{
		$conn = Application::getConnection();
		$helper = $conn->getSqlHelper();
		$conn->query("DELETE FROM ".$helper->quote(self::getTableName())." WHERE PROFILE_ID=" . $ID);
		unset($helper, $conn);
	}


	public static function getNextTask()
	{
		$conn = Application::getConnection();
		$helper = $conn->getSqlHelper();
		$strSql = "SELECT * FROM " . $helper->quote(self::getTableName()) . " WHERE " .
			$helper->quote('UNIX_TIMESTAMP') . "<=" . time() . " AND " .
			$helper->quote('STATE') . " = 'WT' AND " .
			$helper->quote('TYPE') . " = 'CT'";
		$result = $conn->query($strSql);
		$task = $result->Fetch();
		if(!$task) {
			self::scheduleCheckTasks();
			$strSql = "SELECT * FROM " . $helper->quote(self::getTableName()) . " WHERE " .
				$helper->quote('UNIX_TIMESTAMP') . "<=" . time() . " AND " .
				$helper->quote('STATE') . " = 'WT' AND " .
				$helper->quote('TYPE') . " = 'SP' ORDER BY UNIX_TIMESTAMP ASC";
			$result = $conn->query($strSql);
			$task = $result->Fetch();
			if (!$task) {
				$strSql = "SELECT * FROM " . $helper->quote(self::getTableName()) . " WHERE " .
					$helper->quote('UNIX_TIMESTAMP') . "<=" . time() . " AND " .
					$helper->quote('STATE') . " = 'WT' AND " .
					$helper->quote('TYPE') . " != 'HP' AND " .
					$helper->quote('TYPE') . " != 'UP' AND " .
					$helper->quote('TYPE') . " != 'SP' AND " .
					$helper->quote('TYPE') . " != 'ST' AND " .
					$helper->quote('TYPE') . " != 'PR' ORDER BY UNIX_TIMESTAMP ASC";
				$result = $conn->query($strSql);
				$task = $result->Fetch();
			}
		}
		unset($helper, $conn);
		return $task;
	}


	public static function executeNextTask()
	{
		Loader::includeModule("catalog");
		if( $task = self::getNextTask() ) {
			Option::set(self::$moduleID, "last_task_time", time());
			$arFields = ["STATE" => "IW"];
			self::update($task["ID"], $arFields);
			if( $task["TYPE"] == "RQ" ) {
				self::repeatQuery($task);
			}
			if( $task["TYPE"] == "PU" || $task["TYPE"] == "DU" ) {
				self::updateProduct($task);
			}
			if( $task["TYPE"] == "SP" ) {
				self::sendPriceNStocks($task);
			}
			if( $task["TYPE"] == "HS" ) {
				self::sendHidden($task);
			}
			if( $task["TYPE"] == "US" ) {
				self::sendShown($task);
			}
			if( $task["TYPE"] == "CT" ) {
				self::checkTasks($task);
			}
			//sleep(1);
			//usleep(500000);
			$v = randString(12, "0123456789");
			$comm = "wget --no-check-certificate ––tries=0 -b -q -O - https://" .
				Option::get(self::$moduleID, "domen") . "/bitrix/services/iplogic/mkpapi/task.php?v=" . $v;
			exec( $comm );
			//die();
		}
		else {
			if(
				Option::get(self::$moduleID, "can_execute_tasks", "N") == "N"
				&& Option::get(self::$moduleID, "allow_multichain_tasks", "N") == "N"
			) {
				Option::set(self::$moduleID, "can_execute_tasks", "Y");
			}
		}
	}


	/* QUERY */

	protected static function repeatQuery($task)
	{
		$obApi = new YMAPI($task["PROFILE_ID"]);
		$arLog = ApiLogTable::getById($task["ENTITY_ID"]);
		if( $arLog ) {
			$res = $obApi->query($arLog["REQUEST_TYPE"], $arLog["URL"], $arLog["REQUEST"], $task);
			if( $res["status"] == 200 ) {
				self::delete($task["ID"]);
				return;
			}
			if( $res["stop_repeating"] ) {
				self::delete($task["ID"]);
				$arFields = [
					"STATE" => "RJ",
				];
				ApiLogTable::update($task["ENTITY_ID"], $arFields);
			}
			else {
				$arFields = [
					"STATE"          => "WT",
					"TRYING"         => ($task["TRYING"] + 1),
					"UNIX_TIMESTAMP" => time() + Option::get(self::$moduleID, "task_trying_period", 60),
				];
				self::update($task["ID"], $arFields);
			}
		}
		else {
			self::delete($task["ID"]);
		}
	}


	/* CHECK TASKS */

	public static function scheduleCheckTasks()
	{
		self::scheduleTask(0, "CT", 600);
	}

	protected static function checkTasks($task)
	{
		$conn = Application::getConnection();
		$helper = $conn->getSqlHelper();
		$strSql = "SELECT * FROM " . $helper->quote(self::getTableName()) . " WHERE " .
			$helper->quote('UNIX_TIMESTAMP') . "<=" . (time()-300) . " AND " .
			$helper->quote('STATE') . " = 'IW'";
		$result = $conn->query($strSql);
		while ($_task = $result->Fetch()) {
			self::delete($_task["ID"]);
		}
		unset($helper, $conn);
		self::delete($task["ID"]);
		self::scheduleCheckTasks();
		return;
	}


	/* PRODUCT */

	protected static function updateProduct($task)
	{
		ProductTable::updateCache($task["ENTITY_ID"]);
		self::delete($task["ID"]);
	}


	/* PRICE AND STOCKS */

	public static function addPriceUpdateTask($ID, $PROFILE_ID)
	{
		if(Option::get(self::$moduleID, "send_prices") == "Y") {
			$rsTask = self::getList(
				["filter" => ["TYPE" => "PR", "STATE" => "WT", "ENTITY_ID" => $ID, "PROFILE_ID" => $PROFILE_ID]]
			);
			if( !$rsTask->Fetch() ) {
				$arFields = [
					"PROFILE_ID"     => $PROFILE_ID,
					"UNIX_TIMESTAMP" => time(),
					"TYPE"           => "PR",
					"STATE"          => "WT",
					"ENTITY_ID"      => $ID,
					"TRYING"         => 0,
				];
				self::add($arFields);
				self::scheduleSendPriceNStocks($PROFILE_ID);
			}
		}
	}

	public static function addStockUpdateTask($ID, $PROFILE_ID)
	{
		$mod = new Control($PROFILE_ID);
		if((int)$mod->arProfile["STORE"] > 0 && Option::get(self::$moduleID, "send_stocks") == "Y") {
			$rsTask = self::getList(
				["filter" => ["TYPE" => "ST", "STATE" => "WT", "ENTITY_ID" => $ID, "PROFILE_ID" => $PROFILE_ID]]
			);
			if( !$rsTask->Fetch() ) {
				$arFields = [
					"PROFILE_ID"     => $PROFILE_ID,
					"UNIX_TIMESTAMP" => time(),
					"TYPE"           => "ST",
					"STATE"          => "WT",
					"ENTITY_ID"      => $ID,
					"TRYING"         => 0,
				];
				self::add($arFields);
				self::scheduleSendPriceNStocks($PROFILE_ID);
			}
		}
	}

	public static function schedulesendPriceNStocks($PROFILE_ID)
	{
		self::scheduleTask($PROFILE_ID, "SP", 60);
	}

	public static function sendPriceNStocks($task)
	{
		$mod = new Control($task["PROFILE_ID"]);
		if(Option::get(self::$moduleID, "send_prices") == "Y") {
			$rsData = self::getList(
				[
					"filter" => ["TYPE" => "PR", "PROFILE_ID" => $task["PROFILE_ID"]],
					"order"  => ["UNIX_TIMESTAMP" => "ASC"],
					'limit'  => 500,
					'offset' => 0,
				]
			);
			$IDs = [];
			$arProducts = [];
			while( $arData = $rsData->Fetch() ) {
				$IDs[$arData["ID"]] = $arData["ENTITY_ID"];
			}
			if( count($IDs) ) {
				$rsData = ProductTable::getList(
					[
						"filter" => ["ID" => $IDs],
					]
				);
				while( $arData = $rsData->Fetch() ) {
					$arProducts[$arData["ID"]] = $arData;
				}
			}
			if( count($arProducts) ) {
				$arPrices = [];
				$arOldPrices = [];
				$arResult['offers'] = [];
				$arMarketSKUs = [];
				foreach( $IDs as $key => $val ) {
					if(
						!isset($arProducts[$val]) ||
						/*!$arProducts[$val]["MARKET_SKU"] ||
						in_array($arProducts[$val]["MARKET_SKU"], $arMarketSKUs)*/
						!$arProducts[$val]["SKU_ID"] ||
						in_array($arProducts[$val]["SKU_ID"], $arMarketSKUs)
					) {
						self::delete($key);
					}
					else {
						$details = unserialize($arProducts[$val]["DETAILS"]);
						if( $details["PRICE"] > 0 ) {
							//$arMarketSKUs[] = $arProducts[$val]["MARKET_SKU"];
							$arMarketSKUs[] = $arProducts[$val]["SKU_ID"];
							$new_price = $details["PRICE"];
							$old_price = $details["OLD_PRICE"];
							$arPrices[$val] = $new_price;
							$price = [
								"currencyId" => "RUR",
								"value"      => (double)$new_price,
							];
							if( $old_price > $new_price ) {
								$price["discountBase"] = (double)$old_price;
								$arOldPrices[$val] = $old_price;
							}
							$arResult['offers'][] = [
								//"marketSku" => $arProducts[$val]["MARKET_SKU"],
								"offerId"   => $arProducts[$val]["SKU_ID"],
								"delete"    => false,
								"price"     => $price,
							];
						}
						else {
							self::delete($key);
						}
					}
				}

				$api = new YMAPI($task["PROFILE_ID"]);
				$res = $api->setPrices($arResult);
				if( $res["status"] == 200 ) {
					foreach( $IDs as $key => $val ) {
						if( array_key_exists($val, $arPrices) ) {
							$arFields = [
								"PRICE" => $arPrices[$val],
								"PRICE_TIME" => date('d.m.Y H:i:s', time())
							];
							if(isset($arOldPrices[$val])) {
								$arFields["OLD_PRICE"] = $arOldPrices[$val];
							}
							else{
								$arFields["OLD_PRICE"] = "";
							}
							$res = ProductTable::update($val, $arFields);
							self::delete($key);
						}
					}
				}
				else {
					//AddMessage2Log('Price update error', 'iplogic.beru');
				}
			}
		}
		if(Option::get(self::$moduleID, "send_stocks") == "Y") {
			$bContinue = true;
			$steps = 1;
			while ($bContinue == true) {
				$rsData = self::getList(
					[
						"filter" => ["TYPE" => "ST", "PROFILE_ID" => $task["PROFILE_ID"]],
						"order"  => ["UNIX_TIMESTAMP" => "ASC"],
						'limit'  => 2000,
						'offset' => 0,
					]
				);
				$IDs = [];
				$arProducts = [];
				while( $arData = $rsData->Fetch() ) {
					$IDs[$arData["ID"]] = $arData["ENTITY_ID"];
				}
				if( count($IDs) ) {
					$rsData = ProductTable::getList(
						[
							"filter" => ["ID" => $IDs],
						]
					);
					while( $arData = $rsData->Fetch() ) {
						$arProducts[$arData["ID"]] = $arData;
					}
				}
				if( count($arProducts) ) {
					$arStocks = [];
					$arResult['offers'] = [];
					$arRequest = [];
					$arRequest["skus"] = [];
					foreach( $IDs as $key => $val ) {
						if(
							!isset($arProducts[$val]) ||
							!$arProducts[$val]["SKU_ID"] ||
							(int)$mod->arProfile["STORE"] <= 0
						) {
							self::delete($key);
						}
						else {
							$details = unserialize($arProducts[$val]["DETAILS"]);
							if( $details["STOCK_FIT"] != "" ) {
								$arStocks[$val] = $details["STOCK_FIT"];
								$arSelect = ["STOCK_FIT", "CHANGE_TIME"];
								$arFeatures = $mod->getSKU((string)$arProducts[$val]["SKU_ID"], $arSelect);
								$arRequest["skus"][] = [
									"sku"         => (string)$arProducts[$val]["SKU_ID"],
									"warehouseId" => (int)$mod->arProfile["STORE"],
									"items"       => [
										[
											"count"     => (int)$details["STOCK_FIT"],
											"type"      => "FIT",
											"updatedAt" => $arFeatures["CHANGE_TIME"]
										]
									]
								];
							}
							else {
								self::delete($key);
							}
						}
					}

					if (count($arRequest["skus"])) {
						$api = new YMAPI($task["PROFILE_ID"]);
						$res = $api->setStocks($arRequest);
						if( $res["status"] == 200 ) {
							foreach( $IDs as $key => $val ) {
								if( array_key_exists($val, $arStocks) ) {
									$res = ProductTable::update($val, [
										"STOCK_FIT" => $arStocks[$val],
										"STOCK_TIME" => date('d.m.Y H:i:s', time())
									]);
									self::delete($key);
								}
							}
						}
						else {
							//AddMessage2Log('Price update error', 'iplogic.beru');
						}
					}

					$steps++;
					if($steps > 500) {
						$bContinue = false;
					}
				}
				else {
					$bContinue = false;
				}
			}
		}
		self::delete($task["ID"]);
		self::scheduleSendPriceNStocks($task["PROFILE_ID"]);
	}


	/* HIDE */


	public static function hideProductTask($ID, $PROFILE_ID)
	{
		$rsTask = self::getList(["filter" => ["TYPE" => "HP", "STATE" => "WT", "ENTITY_ID" => $ID]]);
		if( !$rsTask->Fetch() ) {
			$arFields = [
				"PROFILE_ID"     => $PROFILE_ID,
				"UNIX_TIMESTAMP" => time(),
				"TYPE"           => "HP",
				"STATE"          => "WT",
				"ENTITY_ID"      => $ID,
				"TRYING"         => 0,
			];
			self::add($arFields);
			self::scheduleSendHidden($PROFILE_ID);
		}
	}


	public static function scheduleSendHidden($PROFILE_ID)
	{
		self::scheduleTask($PROFILE_ID, "HS", 60);
	}


	protected static function sendHidden($task)
	{
		$rsData = self::getList(
			[
				"filter" => ["TYPE" => "HP", "PROFILE_ID" => $task["PROFILE_ID"]],
				"order"  => ["UNIX_TIMESTAMP" => "ASC"],
				'limit'  => 500,
				'offset' => 0,
			]
		);
		$IDs = [];
		$arProducts = [];
		while( $arData = $rsData->Fetch() ) {
			$IDs[$arData["ID"]] = $arData["ENTITY_ID"];
		}
		if( count($IDs) ) {
			$rsData = ProductTable::getList(
				[
					"filter" => ["ID" => $IDs],
				]
			);
			while( $arData = $rsData->Fetch() ) {
				$arProducts[$arData["ID"]] = $arData;
			}
		}
		if( count($arProducts) ) {
			$arResult['hiddenOffers'] = [];
			$unseted = [];
			foreach( $IDs as $key => $val ) {
				if( !isset($arProducts[$val]) || !$arProducts[$val]["MARKET_SKU"] ) {
					$unseted[] = $val;
					self::delete($key);
				}
				else {
					$arResult['hiddenOffers'][] = [
						"marketSku"  => (int)$arProducts[$val]["MARKET_SKU"],
						"comment"    => "",
						"ttlInHours" => 720,
					];
				}
			}
			$api = new YMAPI($task["PROFILE_ID"]);
			$res = $api->setHidden($arResult);
			if( $res["status"] == 200 ) {
				foreach( $IDs as $key => $val ) {
					if( !in_array($val, $unseted) ) {
						ProductTable::update($val, ["HIDDEN" => "Y"]);
						self::delete($key);
					}
				}
			}
			else {
				//AddMessage2Log('Hidden update error', 'iplogic.beru');
			}
		}
		self::delete($task["ID"]);
		self::scheduleSendHidden($task["PROFILE_ID"]);
	}


	/* SHOW */


	public static function showProductTask($ID, $PROFILE_ID)
	{
		$rsTask = self::getList(["filter" => ["TYPE" => "UP", "STATE" => "WT", "ENTITY_ID" => $ID]]);
		if( !$rsTask->Fetch() ) {
			$arFields = [
				"PROFILE_ID"     => $PROFILE_ID,
				"UNIX_TIMESTAMP" => time(),
				"TYPE"           => "UP",
				"STATE"          => "WT",
				"ENTITY_ID"      => $ID,
				"TRYING"         => 0,
			];
			self::add($arFields);
			self::scheduleSendShown($PROFILE_ID);
		}
	}


	public static function scheduleSendShown($PROFILE_ID)
	{
		self::scheduleTask($PROFILE_ID, "US", 60);
	}


	protected static function sendShown($task)
	{
		$rsData = self::getList(
			[
				"filter" => ["TYPE" => "UP", "PROFILE_ID" => $task["PROFILE_ID"]],
				"order"  => ["UNIX_TIMESTAMP" => "ASC"],
				'limit'  => 500,
				'offset' => 0,
			]
		);
		$IDs = [];
		while( $arData = $rsData->Fetch() ) {
			$IDs[$arData["ID"]] = $arData["ENTITY_ID"];
		}
		if( count($IDs) ) {
			$rsData = ProductTable::getList(
				[
					"filter" => ["ID" => $IDs],
				]
			);
			$arProducts = [];
			while( $arData = $rsData->Fetch() ) {
				$arProducts[$arData["ID"]] = $arData;
			}
		}
		if( count($arProducts) ) {
			$arResult['hiddenOffers'] = [];
			$unseted = [];
			foreach( $IDs as $key => $val ) {
				if( !isset($arProducts[$val]) || !$arProducts[$val]["MARKET_SKU"] ) {
					$unseted[] = $val;
					self::delete($key);
				}
				else {
					$arResult['hiddenOffers'][] = [
						"marketSku" => (int)$arProducts[$val]["MARKET_SKU"],
					];
				}
			}
			$api = new YMAPI($task["PROFILE_ID"]);
			$res = $api->setShown($arResult);
			if( $res["status"] == 200 ) {
				foreach( $IDs as $key => $val ) {
					if( !in_array($val, $unseted) ) {
						ProductTable::update($val, ["HIDDEN" => "N"]);
						self::delete($key);
					}
				}
			}
			else {
				//AddMessage2Log('Hidden update error', 'iplogic.beru');
			}
		}
		self::delete($task["ID"]);
		self::scheduleSendShown($task["PROFILE_ID"]);
	}


	/* COMMON */


	public static function scheduleTask($PROFILE_ID, $CODE, $DELAY)
	{
		/*$conn = Application::getConnection();
		$helper = $conn->getSqlHelper();
		$strSql =
			"SELECT * FROM " . $helper->quote(self::getTableName()) . " WHERE " . $helper->quote('TYPE') . " = '" .
			$CODE . "' AND " . $helper->quote('PROFILE_ID') . " = " . $PROFILE_ID . " AND " .
			$helper->quote('STATE') . " = 'WT'";
		$result = $conn->query($strSql);
		unset($helper, $conn);*/
		$result = self::getList(
			["filter" => ["TYPE" => $CODE, "STATE" => "WT", "PROFILE_ID" => $PROFILE_ID]]
		);
		$task = $result->Fetch();
		if( !$task ) {
			$arFields = [
				"PROFILE_ID"     => $PROFILE_ID,
				"UNIX_TIMESTAMP" => time() + $DELAY,
				"TYPE"           => $CODE,
				"STATE"          => "WT",
				"TRYING"         => 0,
			];
			self::add($arFields);
		}
	}

}


