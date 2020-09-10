<?php
class SBIS {
	private $sid;
	private $token;
	
	public $error = '';

	public function request($url, $json = false, $data = [], $headers = []) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

		if ($data) {
			curl_setopt($ch, CURLOPT_POST, 1);
			if ($json) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			} else {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			}
		}

		if ($headers) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$response = curl_exec($ch);
		curl_close($ch);

		if ($json) {
			$response = @json_decode($response, true);
		}

		return $response;
	}

	public function auth($app_id, $app_secret, $secret_key) {
		$data = $this->request('https://online.sbis.ru/oauth/service/', true, ['app_client_id' => $app_id, 'app_secret' => $app_secret, 'secret_key' => $secret_key]);

		if (gettype($data) == 'array') {
			$this->sid = $data['sid'];
			$this->token = $data['token'];
			$this->error = '';
		} else {
			$this->error = $response;
		}
	}

	public function deauth() {
		if ($this->token) {
			$this->request('https://online.sbis.ru/oauth/service/', true, ['event' => 'exit', 'token' => $this->token]);

			$this->sid = null;
			$this->token = null;
			$this->error = '';
		}
	}

	public function getSalesPoints() {
		$data = $this->request('https://api.sbis.ru/retail/point/list', true, [], ['X-SBISSessionId: ' . $this->sid, 'X-SBISAccessToken: ' . $this->token]);

		if (gettype($data) == 'array' && array_key_exists('salesPoints', $data)) {
			return $data['salesPoints'];
		}
	}

	public function getPriceLists($point_id) {
		$data = $this->request('https://api.sbis.ru/retail/nomenclature/price-list?pointId=' . $point_id . '&actualDate=' . time(), true, [], ['X-SBISSessionId: ' . $this->sid, 'X-SBISAccessToken: ' . $this->token]);
		
		if (gettype($data) == 'array' && array_key_exists('priceLists', $data)) {
			return $data['priceLists'];
		}
	}

	public function getProducts($point_id, $price_list_id, $limit = 10000) {
		$products = [];

		$page = 0;
		$hasMore = true;
		while ($hasMore && count($products) < $limit) {
			$data = $this->request('https://api.sbis.ru/retail/nomenclature/list?pointId=' . $point_id . '&priceListId=' . $price_list_id . '&pageSize=' . ($limit > 1 ? $limit - 1 : 1) . '&page=' . $page . '&withBalance=true', true, [], ['X-SBISSessionId: ' . $this->sid, 'X-SBISAccessToken: ' . $this->token]);

			if (gettype($data) == 'array' && array_key_exists('nomenclatures', $data)) {
				$hasMore = $data['outcome']['hasMore'];
				$products = array_merge($products, $data['nomenclatures']);
			} else {
				return $response;
			}

			if ($hasMore) {
				$page++;
			}
		}

		return $products;
	}

	public function saveImage($url, $name) {
		$filename = DIR_IMAGE . 'catalog/' . $name . '.jpg';

		if (!is_file($filename)) {
			$response = $this->request('https://api.sbis.ru/retail' . $url, false, [], ['X-SBISSessionId: ' . $this->sid, 'X-SBISAccessToken: ' . $this->token]);
			file_put_contents($filename, $response);
		}

		return str_replace(DIR_IMAGE, '', $filename);
	}

	public function __destruct() {
		$this->deauth();
	}
}