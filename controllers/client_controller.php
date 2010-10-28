<?php
/**
 * Client Controller
 * 
 * @package cakeftp
 * @author Kyle Robinson Young, kyletyoung.com
 * @copyright 2010 Kyle Robinson Young
 */
class ClientController extends FtpAppController {
	public $uses = array('Ftp.Ftp');
	public $components = array('Session');
	public $helpers = array('Ftp.Ftp');
	public $connected = false;
	public $tmp_file = 'cakeftp_download_tmpfile';

/**
 * beforeFilter
 * Try to connect
 */
	function beforeFilter() {
		parent::beforeFilter();
		if (file_exists(TMP.$this->tmp_file)) {
			@unlink(TMP.$this->tmp_file);
		}
		if (isset($this->data['Ftp'])) {
			$info = $this->data['Ftp'];
			$path = $this->data['Ftp']['path'];
		} elseif ($this->Session->check('ftp')) {
			$info = $this->Session->read('ftp');
		}
		try {
			if ($this->action != 'logout') {
				$this->connected = $this->Ftp->connect();
				if ($this->connected === false && isset($info)) {
					$this->connected = $this->Ftp->connect(array(
						'host' => $info['host'],
						'username' => $info['username'],
						'password' => $info['password'],
						'type' => $info['type'],
					));
					$this->Session->write('ftp', $info);
				}
			}
		} catch (Exception $e) {
			$this->Session->setFlash($e->getMessage());
		}
		$this->set('connected', $this->connected);
	}

/**
 * index
 * Browse remote files
 * 
 * @param string $path
 */
	function index($path=null) {
		$connected = false;
		if (isset($path)) {
			$path = base64_decode(urldecode($path));
		}
		if ($this->connected) {
			try {
				$files = $this->Ftp->find('all', array(
					'conditions' => array(
						'path' => $path,
					),
				));
				$path = $this->Ftp->id;
			} catch (Exception $e) {
				debug($e->getMessage());
			}
		}
		if (dirname($path) != $path) {
			$parent = urlencode(base64_encode(dirname($path)));
		}
		$this->set(compact('files', 'path', 'parent'));
	}

/**
 * upload
 */
	function upload() {
		if (!empty($this->data['File']) && $this->connected) {
			try {
				$remote = $this->data['File']['path'].DS.$this->data['File']['file']['name'];
				if ($this->Ftp->save(array(
					'local' => $this->data['File']['file']['tmp_name'],
					'remote' => $remote,
				))) {
					$this->Session->setFlash(__d('cakeftp', 'I got that thing you sent me', true));
				}	
			} catch (Exception $e) {
				$this->Session->setFlash($e->getMessage());
			}
		}
		$path = (isset($this->data['File']['path'])) ? $this->data['File']['path'] : '';
		$this->redirect(array(
			'plugin' => 'ftp',
			'controller' => 'client',
			'action' => 'index', urlencode(base64_encode($path)),
		));
		exit;
	}

/**
 * download
 * @param string $path
 */
	function download($path=null) {
		$path = base64_decode(urldecode($path));
		if ($this->connected) {
			$pathinfo = pathinfo($path);
			try {
				if ($this->Ftp->save(array(
					'local' => TMP.$this->tmp_file,
					'remote' => $path,
					'direction' => 'down',
				))) {
					$this->view = 'Media';
					$this->autoLayout = false;
					$params = array(
						'id' => $this->tmp_file,
						'name' => substr($pathinfo['basename'], 0, strrpos($pathinfo['basename'], '.')),
						'download' => true,
						'extension' => $pathinfo['extension'],
						'path' => TMP,
					);
					$this->set($params);
				}
			} catch (Exception $e) {
				@unlink(TMP.$this->tmp_file);
				$this->Session->setFlash($e->getMessage());
			}
		}
	}

/**
 * delete
 * @param string $path
 */
	function delete($path=null) {
		$path = base64_decode(urldecode($path));
		if ($this->connected) {
			try {
				if ($this->Ftp->delete($path)) {
					$this->Session->setFlash(__d('cakeftp', 'Goodbye and good riddance!', true));
				}
			} catch (Exception $e) {
				$this->Session->setFlash($e->getMessage());
			}
		}
		$path = urlencode(base64_encode(dirname($path)));
		$this->redirect(array(
			'plugin' => 'ftp',
			'controller' => 'client',
			'action' => 'index', $path,
		));
		exit;
	}

/**
 * logout
 */
	function logout() {
		$this->Session->delete('ftp');
		$this->redirect(array(
			'plugin' => 'ftp',
			'controller' => 'client',
			'action' => 'index',
		));
		exit;
	}
}