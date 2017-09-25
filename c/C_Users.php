<?php 
include_once 'm/M_Users.php';

class C_Users extends C_Base {

	public function getUser($id) {
		$mUsers = new M_Users;
		$user = $mUsers->Get($id);
		return json_encode($user);
	}

	public function addUser($UserParams, $UserPhoto = null) {
		$user = $mUsers->addUser($UserParams, $UserPhoto);
		return json_encode($user);
	}

	public function editUser($UserParams) {
		$user = $mUsers->editUser(json_decode($UserParams, $assoc = true));
		return json_encode($user);
	}
}