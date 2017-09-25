<?php
include_once('M_Mysql.php');

try{ 
class M_Users
{	
		private static $instance;	// экземпляр класса
		private $msql;				// драйвер БД
		private $sid;				// идентификатор текущей сессии
		private $uid;				// идентификатор текущего пользователя
		
		
		// Получение экземпляра класса
		// результат	- экземпляр класса MSQL
		
		public static function Instance()
		{
			if (self::$instance == null)
				self::$instance = new M_Users();
				
			return self::$instance;
		}

		//
		// Конструктор
		//
		public function __construct()
		{
			$this->msql = M_Mysql::getInstance();
			$this->sid = null;
			$this->uid = null;
		}
		
		//
		// Очистка неиспользуемых сессий
		// 
		public function ClearSessions()
		{
			$min = date('Y-m-d H:i:s', time() - 60 * 20); 			
			$t = "time_last < '%s'";
			$where = sprintf($t, $min);
			$this->msql->Delete('Sessions', $where);
		}

		//
		// Авторизация
		// $login 		- логин
		// $password 	- пароль
		// $remember 	- нужно ли запомнить в куках
		// результат	- true или false
		//

		public function Login($login, $password, $remember = true)
		{
			// вытаскиваем пользователя из БД 
			$user = $this->GetByLogin($login);

			if ($user == null)
				return false;
			
			$id_user = $user['id'];
			
			// проверяем пароль
			if ($user['UserPassHash'] != md5($password))
				return false;
					
			// запоминаем имя и md5(пароль)
			if ($remember)
			{
				$expire = time() + 3600 * 24 * 100;
				setcookie('login', $login, $expire);
				setcookie('password', md5($password), $expire);
			}		
					
			// открываем сессию и запоминаем SID
			$this->sid = $this->OpenSession($id_user);
			
			return true;
		}
		
		//
		// Деавторизация
		//
		public function Logout()
		{
			setcookie('login', '', time() - 1);
			setcookie('password', '', time() - 1);
			unset($_COOKIE['login']);
			unset($_COOKIE['password']);
			unset($_SESSION['sid']);		
			$this->sid = null;
			$this->uid = null;
		}
							
		
		// Получение пользователя
		// $id_user		- если не указан, брать текущего
		// результат	- объект пользователя
		
		public function Get($id_user = null)
		{	
			// Если id_user не указан, берем его по текущей сессии.
			if ($id_user == null) {
				$id_user = $this->GetUid();
			}
			if ($id_user == null) {
				return null;
			}	
			
			$t = "SELECT * FROM Users WHERE id = '%d'";
			
			$query = sprintf($t, mysqli_real_esxape_string($this->link, $id_user));
			
			$result = $this->msql->Select($query);
			return $result[0];		
		}
		
		
		// Получает пользователя по логину
		
		public function GetByLogin($login)
		{	
			$t = "SELECT * FROM users WHERE UserLogin = '%s'";
			$query = sprintf($t, $login);
			$result = $this->msql->Select($query);
			return $result[0];
		}
		// Получает пользователя по электронной почте
		public function GetByEmail($email)
		{	
			$t = "SELECT * FROM users WHERE UserEmail = '%s'";
			$query = sprintf($t, mysqli_real_esxape_string($this->link, $email));
			$result = $this->msql->Select($query);
			return $result[0];
		}
				
		//
		// Получение id текущего пользователя
		// результат	- UID
		//
		public function GetUid()
		{	
			// Проверка кеша.
			if ($this->uid != null)
				return $this->uid;	

			// Берем по текущей сессии.
			$sid = $this->GetSid();
					
			if ($sid == null)
				return null;
				
			$t = "SELECT id_user FROM Sessions WHERE SID = '%s'";
			$query = sprintf($t, mysqli_real_escape_string($this->link, $sid));
			$result = $this->msql->Select($query);
					
			// Если сессию не нашли - значит пользователь не авторизован.
			if (count($result) == 0) {
				return null;
			}
				
			// Если нашли - запоминм ее.
			$this->uid = $result[0]['id'];
			return $this->uid;
		}

		//
		// Функция возвращает идентификатор текущей сессии
		// результат	- SID
		//
		private function GetSid()
		{
			// Проверка кеша.
			if ($this->sid != null)
				return $this->sid;
		
			// Ищем SID в сессии.
			$sid = @$_SESSION['SID'];
									
			// Если нашли, попробуем обновить time_last в базе. 
			// Заодно и проверим, есть ли сессия там.
			if ($sid != null)
			{
				$session = array();
				$session['time_last'] = date('Y-m-d H:i:s'); 			
				$t = "SID = '%s'";
				$where = sprintf($t, mysql_real_escape_string($sid));
				$affected_rows = $this->msql->Update('Sessions', $session, $where);

				if ($affected_rows == 0)
				{
					$t = "SELECT count(*) FROM Sessions WHERE SID = '%s'";		
					$query = sprintf($t, mysqli_real_escape_string($this->link, $sid));
					$result = $this->msql->Select($query);
					
					if ($result[0]['count(*)'] == 0)
						$sid = null;			
				}			
			}		
			
			//Если нет сессии ищем логин и md5(пароль) в куках.
			if ($sid == null && isset($_COOKIE['login']))
			{
				$user = $this->GetByLogin($_COOKIE['login']);
				
				if ($user != null && $user['UserPasswdHash'] == $_COOKIE['password'])
					$sid = $this->OpenSession($user['id']);
			}
			
			// Запоминаем в кеш.
			if ($sid != null) {
				$this->sid = $sid;
			}
			// Возвращаем SID.
			return $sid;		
		}
		
		//
		// Открытие новой сессии
		// результат	- SID
		//
		private function OpenSession($id_user)
		{
			// генерируем SID
			$sid = $this->GenerateStr(10);
					
			// вставляем SID в БД
			$now = date('Y-m-d H:i:s'); 
			$session = array();
			$session['REF_USER_ID'] = $id_user;
			$session['SID'] = $sid;
			$session['TimeStart'] = $now;
			$session['TimeLast'] = $now;				
			$this->msql->Insert('Sessions', $session); 
					
			// регистрируем сессию в PHP сессии
			$_SESSION['SID'] = $sid;				
					
			// возвращаем SID
			return $sid;	
		}

		//
		// Генерация случайной последовательности
		// $length 		- ее длина
		// результат	- случайная строка
		//
		private function GenerateStr($length = 10) 
		{
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPRQSTUVWXYZ0123456789";
			$code = "";
			$clen = strlen($chars) - 1;  

			while (strlen($code) < $length) 
				$code .= $chars[mt_rand(0, $clen)];  

			return $code;
		}
		
		public function addUser($UserInfo, $UserPhoto) {
			
			if (!isset($UserInfo['UserLogin']) || $UserInfo['UserLogin'] == '') {
				throw new Exception('Не указан логин');
			}
			if (!isset($UserInfo['UserPass']) || $UserInfo['UserPass'] == '') {
				throw new Exception('Не указан пароль');
			}
			if (!isset($UserInfo['UserName']) || $UserInfo['UserName'] == '') {
				throw new Exception('Не указано имя пользователя');
			}
			if (!isset($UserInfo['UserEmail']) || $UserInfo['UserEmail'] == '') {
				throw new Exception('Не указана электронная почта');
			}
			
			$user = $this->GetByLogin($UserInfo['UserLogin']);
			if ($user <> null) {
				throw new Exception('Пользователь с таким логином уже существует');
			}
			
			$user = $this->GetByEmail($UserInfo['UserEmail']);
			if ($user['UserEmail'] == $UserInfo['UserEmail']) {
				throw new Exception('Пользователь с такой электронной почтой уже зарегистрирован');
			}
			$UserInfo['UserPasswdHash'] = md5($UserInfo['UserPass']);
			unset($UserInfo['UserPass']);
			$newUserId = $this->msql->Insert('Users', $UserInfo);
			
			if (isset($UserPhoto)) {
				$uploaddir = 'data/';
				$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);
				move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile);
				$newPhotoId = $this->msql->Insert('Docs', array('REF_DOC_TYPE'=> '1', 'REF_USER_ID'=>$newUserId, 'DocPath' =>$uploadfile) );
				$this->msql->Insert('Users', array('REF_USER_PHOTO_ID'=> $newPhotoId) );
			}
			return $this->msql->Get($newUserId);
		}
		
		public function editUser($UserInfo) {
			
			if (!isset($UserInfo['id']) || $UserInfo['id'] == '') {
				throw new Exception('Не указан пользователь');
			}
			$user = $this->Get($UserInfo['id']);
			
			if ($user == null) {
				throw new Exception('Пользователя не существует');
			}
			
			if (isset($UserInfo['UserLogin']) && $UserInfo['UserLogin'] == '') {
				throw new Exception('Не указан логин');
			}
			if (isset($UserInfo['UserPass']) && $UserInfo['UserPass'] == '') {
				throw new Exception('Не указан пароль');
			}
			if (isset($UserInfo['UserName']) && $UserInfo['UserName'] == '') {
				throw new Exception('Не указано имя пользователя');
			}
			if (isset($UserInfo['UserEmail']) && $UserInfo['UserEmail'] == '') {
				throw new Exception('Не указана электронная почта');
			}
			if (isset($UserInfo['UserPass'])) {
				$UserInfo['UserPasswdHash'] = md5($UserInfo['UserPass']);
				unset($UserInfo['UserPass']);
			}
			
			$this->msql->Update('Users', $UserInfo, "id=".$UserInfo['id']."");
			
			$newUserInfo = $this->Get($UserInfo['id']);
			return $newUserInfo;
		}
	}
	
}
catch (Exception $e) {
	echo $e->getMessage();
}
