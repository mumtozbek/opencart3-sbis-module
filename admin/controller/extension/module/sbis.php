<?php
class ControllerExtensionModuleSbis extends Controller {
	private $error = array();

	public function cron() {
		$this->load->language('extension/module/sbis');
		
		$result = $this->import();

		if (gettype($result) == 'string') {
			$this->log->write($result);
			echo $result;
		} else {
			$this->log->write(sprintf($this->language->get('text_success_import'), $result['new_products'], $result['new_categories']));
			printf($this->language->get('text_import'), $result['new_products'], $result['new_categories']);
		}
	}

	public function index() {
		$this->load->language('extension/module/sbis');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->post['module_sbis_sales_points'])) {
				$this->request->post['module_sbis_sales_points'] = (array)$this->config->get('module_sbis_sales_points');
			}
			
			$this->model_setting_setting->editSetting('module_sbis', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/module/sbis', 'user_token=' . $this->session->data['user_token'], true));
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['warning'])) {
			$data['warning'] = $this->session->data['warning'];

			unset($this->session->data['warning']);
		} else {
			$data['warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/sbis', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['exec'] = $this->url->link('extension/module/sbis/exec', 'user_token=' . $this->session->data['user_token'], true);
		$data['auth'] = $this->url->link('extension/module/sbis/auth', 'user_token=' . $this->session->data['user_token'], true);
		$data['action'] = $this->url->link('extension/module/sbis', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if ($this->config->get('module_sbis_app_id') && $this->config->get('module_sbis_app_secret') && $this->config->get('module_sbis_secret_key')) {
			$data['ready_to_auth'] = true;
		} else {
			$data['ready_to_auth'] = false;
		}

		if ($this->config->get('module_sbis_app_id') && $this->config->get('module_sbis_app_secret') && $this->config->get('module_sbis_secret_key') && $this->config->get('module_sbis_sale_point_id') && $this->config->get('module_sbis_price_list_id')) {
			$data['ready_to_import'] = true;
		} else {
			$data['ready_to_import'] = false;
		}

		if (IS_FILE(DIR_CACHE . 'sbis.sale_points.json')) {
			$data['sale_points'] = json_decode(file_get_contents(DIR_CACHE . 'sbis.sale_points.json'), true);
		} else {
			$data['sale_points'] = [];
		}

		if (IS_FILE(DIR_CACHE . 'sbis.price_lists.json')) {
			$data['price_lists'] = json_decode(file_get_contents(DIR_CACHE . 'sbis.price_lists.json'), true);
		} else {
			$data['price_lists'] = [];
		}

		if (isset($this->request->post['module_sbis_status'])) {
			$data['module_sbis_status'] = $this->request->post['module_sbis_status'];
		} else {
			$data['module_sbis_status'] = $this->config->get('module_sbis_status');
		}

		if (isset($this->request->post['module_sbis_app_id'])) {
			$data['module_sbis_app_id'] = $this->request->post['module_sbis_app_id'];
		} else {
			$data['module_sbis_app_id'] = $this->config->get('module_sbis_app_id');
		}

		if (isset($this->request->post['module_sbis_app_secret'])) {
			$data['module_sbis_app_secret'] = $this->request->post['module_sbis_app_secret'];
		} else {
			$data['module_sbis_app_secret'] = $this->config->get('module_sbis_app_secret');
		}

		if (isset($this->request->post['module_sbis_secret_key'])) {
			$data['module_sbis_secret_key'] = $this->request->post['module_sbis_secret_key'];
		} else {
			$data['module_sbis_secret_key'] = $this->config->get('module_sbis_secret_key');
		}

		if (isset($this->request->post['module_sbis_sale_point_id'])) {
			$data['module_sbis_sale_point_id'] = $this->request->post['module_sbis_sale_point_id'];
		} else {
			$data['module_sbis_sale_point_id'] = $this->config->get('module_sbis_sale_point_id');
		}

		if (isset($this->request->post['module_sbis_price_list_id'])) {
			$data['module_sbis_price_list_id'] = $this->request->post['module_sbis_price_list_id'];
		} else {
			$data['module_sbis_price_list_id'] = $this->config->get('module_sbis_price_list_id');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/sbis', $data));
	}

	public function auth() {
		$this->load->model('setting/setting');
		$this->load->language('extension/module/sbis');

		$this->load->library('sbis');
		$this->sbis->auth($this->config->get('module_sbis_app_id'), $this->config->get('module_sbis_app_secret'), $this->config->get('module_sbis_secret_key'));

		if ($this->sbis->error) {
			$this->session->data['warning'] = sprintf($this->language->get('error_auth'), $this->sbis->error);
		} else {
			$sale_points = $this->sbis->getSalesPoints();

			if ($sale_points) {
				if ($this->config->get('module_sbis_sale_point_id')) {
					$price_lists = $this->sbis->getPriceLists($this->config->get('module_sbis_sale_point_id'));
				} else {
					$price_lists = $this->sbis->getPriceLists($sale_points[0]['id']);
				}
			} else {
				$sale_points = [];
				$price_lists = [];
			}

			file_put_contents(DIR_CACHE . 'sbis.sale_points.json', json_encode($sale_points));
			file_put_contents(DIR_CACHE . 'sbis.price_lists.json', json_encode($price_lists));

			$this->session->data['success'] = $this->language->get('text_success_auth');
		}

		$this->sbis->deauth();

		$this->response->redirect($this->url->link('extension/module/sbis', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function exec() {
		$this->load->language('extension/module/sbis');
		
		$result = $this->import();

		if (gettype($result) == 'string') {
			$this->session->data['warning'] = $result;
		} else {
			$this->session->data['success'] = sprintf($this->language->get('text_import'), $result['new_products'], $result['new_categories']);
		}

		$this->response->redirect($this->url->link('extension/module/sbis', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function import() {
		$result = ['new_products' => 0, 'new_categories' => 0];

		$this->load->library('sbis');
		$this->sbis->auth($this->config->get('module_sbis_app_id'), $this->config->get('module_sbis_app_secret'), $this->config->get('module_sbis_secret_key'));
		$products = $this->sbis->getProducts($this->config->get('module_sbis_sale_point_id'), $this->config->get('module_sbis_price_list_id'));

		if (gettype($products) == 'string') {
			return $products;
		}

		$this->load->model('setting/store');
		$stores = $this->model_setting_store->getStores();
		$stores[] = ['store_id' => 0, 'name' => $this->language->get('text_default')];

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('catalog/sbis');

		foreach($products as $item) {
			$alias = $this->model_catalog_sbis->getAlias($item['name']);

			if (isset($item['images'])) {
				$image = $this->sbis->saveImage($item['images'], $alias . '-' . $item['hierarchicalId']);
			} else {
				$image = '';
			}

			if (!empty($item['nomNumber'])) {
				$product = $this->model_catalog_product->getProduct($item['hierarchicalId']);

				if (!empty($product)) {
					$product['product_attribute'] = $this->model_catalog_product->getProductAttributes($product['product_id']);
					$product['product_description'] = $this->model_catalog_product->getProductDescriptions($product['product_id']);
					$product['product_discount'] = $this->model_catalog_product->getProductDiscounts($product['product_id']);
					$product['product_filter'] = $this->model_catalog_product->getProductFilters($product['product_id']);
					$product['product_image'] = $this->model_catalog_product->getProductImages($product['product_id']);
					$product['product_option'] = $this->model_catalog_product->getProductOptions($product['product_id']);
					$product['product_related'] = $this->model_catalog_product->getProductRelated($product['product_id']);
					$product['product_related_article'] = $this->model_catalog_product->getArticleRelated($product['product_id']);
					$product['product_reward'] = $this->model_catalog_product->getProductRewards($product['product_id']);
					$product['product_special'] = $this->model_catalog_product->getProductSpecials($product['product_id']);
					$product['product_category'] = $this->model_catalog_product->getProductCategories($product['product_id']);
					$product['product_download'] = $this->model_catalog_product->getProductDownloads($product['product_id']);
					$product['product_layout'] = $this->model_catalog_product->getProductLayouts($product['product_id']);
					$product['product_store'] = $this->model_catalog_product->getProductStores($product['product_id']);
					$product['product_recurrings'] = $this->model_catalog_product->getRecurrings($product['product_id']);
				}

				foreach($languages as $language) {
					$product['product_description'][$language['language_id']] = ['name' => $item['name'], 'meta_h1' => $item['name'], 'meta_title' => $item['name'], 'meta_description' => $item['description'], 'meta_keyword' => '', 'description' => $item['description'], 'tag' => ''];
				}

				$product['model'] = $item['nomNumber'];
				$product['sku'] = '';
				$product['upc'] = '';
				$product['ean'] = '';
				$product['jan'] = '';
				$product['isbn'] = '';
				$product['mpn'] = '';
				$product['location'] = '';
				$product['price'] = $item['cost'];
				$product['tax_class_id'] = '0';
				$product['quantity'] = (isset($item['balance']) ? $item['balance'] : 0);
				$product['minimum'] = 1;
				$product['subtract'] = 1;
				$product['stock_status_id'] = 5;
				$product['shipping'] = 1;
				$product['date_available'] = date('Y-m-d');
				$product['length'] = '';
				$product['width'] = '';
				$product['height'] = '';
				$product['length_class_id'] = 1;
				$product['weight'] = '';
				$product['weight_class_id'] = 1;
				$product['status'] = 1;//(int)$item['published'];
				$product['sort_order'] = 1;
				$product['manufacturer'] = '';
				$product['manufacturer_id'] = 0;
				$product['category'] = '';
				$product['product_category'] = [$item['hierarchicalParent']];
				$product['main_category_id'] = $item['hierarchicalParent'];
				$product['filter'] = '';
				$product['product_store'] = [0];
				$product['download'] = '';
				$product['related'] = '';
				$product['product_related_article_input'] = '';
				$product['option'] = '';
				$product['image'] = '';
				$product['points'] = '';
				$product['product_reward'] = [];

				foreach($stores as $store) {
					foreach($languages as $language) {
						$product['product_seo_url'][$store['store_id']][$language['language_id']] = ($store['store_id'] ? $store['store_id'] . '-' : '') . $language['language_id'] . '-' . $alias;
					}
				}

				$product['noindex'] = 1;
				$product['product_layout'] = [''];
				$product['image'] = $image;

				if (empty($product['product_id'])) {
					$this->model_catalog_sbis->addProduct($item['hierarchicalId']);
					$result['new_products']++;
				}

				$this->model_catalog_product->editProduct($item['hierarchicalId'], $product);
			} elseif (!empty($item['hierarchicalId'])) {
				$product = $this->model_catalog_category->getCategory($item['hierarchicalId']);
				
				foreach($languages as $language) {
					$product['category_description'][$language['language_id']] = ['name' => $item['name'], 'meta_h1' => $item['name'], 'meta_title' => $item['name'], 'meta_description' => $item['description'], 'meta_keyword' => '', 'description' => $item['description']];
				}

				$product['path'] = '';
				$product['parent_id'] = $item['hierarchicalParent'];
				$product['filter'] = '';
				$product['category_store'] = [0];
				$product['image'] = '';
				$product['top'] = ($item['hierarchicalParent'] ? 0 : 1);
				$product['column'] = 1;
				$product['sort_order'] = 0;
				$product['status'] = (int)$item['published'];

				foreach($stores as $store) {
					foreach($languages as $language) {
						$product['category_seo_url'][$store['store_id']][$language['language_id']] = ($store['store_id'] ? $store['store_id'] . '-' : '') . $language['language_id'] . '-' . $alias;
					}
				}

				$product['noindex'] = 1;
				$product['product_related_input'] = '';
				$product['article_related_input'] = '';
				$product['category_layout'] = [''];
				$product['image'] = $image;

				if (empty($product['category_id'])) {
					$this->model_catalog_sbis->addCategory($item['hierarchicalId']);
					$result['new_categories']++;
				}

				$this->model_catalog_category->editCategory($item['hierarchicalId'], $product);
			}
		}

		return $result;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/sbis')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->error['warning'])) {
			$this->session->data['warning'] = $this->error['warning'];
		}

		return !$this->error;
	}
}