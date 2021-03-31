<?php
/**
 * 2021 Adilis
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 * This code is provided as is without any warranty.
 * No promise of being safe or secure
 *
 * @author   Achard Julien <contact@adilis.fr>
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class CookieBot extends Module
{

	const COOKIE_SUBSCRIPTION_URL = 'https://manage.cookiebot.com/goto/signup?rid=3LJ44';
	const COOKIE_STR_PLACEHOLDER = '<div id="CB_COOKIE_POLICY">{{CB_COOKIE_POLICY}}</div>';

	function __construct()
	{
		$this->name = 'cookiebot';
		$this->author = 'Adilis';
		$this->need_instance = 0;
		$this->bootstrap = true;
		$this->tab = 'front_office_features';
		$this->version = '1.0.1';
		$this->displayName = $this->l('CookieBot');
		$this->description = $this->l('Easy way to in install and customize cookiebot in Prestashop.');

		parent::__construct();
	}

	public function install() {
		return
			parent::install() &&
			$this->installCmsPage() &&
			$this->registerHook('displayHeader') &&
			$this->registerHook('displayFooter');
		;
	}

	public function uninstall() {
		return
			parent::uninstall() &&
			Configuration::deleteByName('CB_ID_DOMAIN_GROUP') &&
			Configuration::deleteByName('CB_MODE_MAINTENANCE')
		;
	}

	public function getContent() {

		$this->context->controller->informations[] = sprintf(
			$this->l('An account on CookieBot is required to make this module work properly. %sClick here to create an account%s'),
			'<a href="'.self::COOKIE_SUBSCRIPTION_URL.'" target="_blank">',
			'</a>'
		);
	    if (\Tools::isSubmit('submit'.$this->name.'Module')) {

	    	if (empty(Tools::getValue('CB_ID_DOMAIN_GROUP'))) {
				$this->context->controller->errors[] = $this->l('Field "Group domain Id" is required');
			}

	        if (!count($this->context->controller->errors)) {

				Configuration::updateValue('CB_ID_DOMAIN_GROUP', Tools::getValue('CB_ID_DOMAIN_GROUP'));
				Configuration::updateValue('CB_MODE_MAINTENANCE', Tools::getValue('CB_MODE_MAINTENANCE'));

	            $redirect_after = $this->context->link->getAdminLink('AdminModules', true);
	            $redirect_after .= '&conf=4&configure='.$this->name.'&module_name='.$this->name;
	            \Tools::redirectAdmin($redirect_after);
	        }
	    }

	    return $this->renderForm();
	}

	public function installCmsPage() {
		if (Configuration::get('CB_ID_CMS_PAGE')) {
			$cms = new Cms((int)Configuration::get('CB_ID_CMS_PAGE'));
			if (Validate::isLoadedObject($cms)) {
				return true;
			}
		}

		$id_cms_category = (int)Db::getInstance()->getValue('
			SELECT id_cms_category
			FROM '._DB_PREFIX_.'cms_category
			'.Shop::addSqlAssociation('cms_category_shop', 'c').'
			WHERE id_parent = 0
		');

		$cms = new CMS();
		$cms->active = 1;
		$cms->indexation = 0;
		$cms->id_cms_category = $id_cms_category;
		$cms->meta_title = array();
		$cms->link_rewrite = array();
		$cms->content = array();

		foreach(Language::getLanguages() as $lang) {
			$id_lang = (int)$lang['id_lang'];
			switch(Tools::strtolower($lang['iso_code'])) {
				case 'fr' : $cms->meta_title[$id_lang] = 'Déclaration relative aux cookies'; break;
				case 'es' : $cms->meta_title[$id_lang] = 'Declaración sobre las cookies'; break;
				case 'it' : $cms->meta_title[$id_lang] = 'Dichiarazione dei cookie'; break;
				case 'de' : $cms->meta_title[$id_lang] = 'Cookie-Erklärung'; break;
				default : $cms->meta_title[$id_lang] = 'Cookie Policy'; break;
			}
			$cms->link_rewrite[$id_lang] = Tools::str2url($cms->meta_title[$id_lang]);
			$cms->content[$id_lang] = self::COOKIE_STR_PLACEHOLDER;
		}

		if ($cms->add()) {
			Configuration::updateValue('CB_ID_CMS_PAGE', (int)$cms->id);
			return true;
		}

		return false;
	}

	private function renderForm() {
	    $helper = new \HelperForm();
	    $helper->show_toolbar = false;
	    $helper->table = $this->table;
	    $helper->module = $this;
	    $helper->default_form_language = $this->context->language->id;
	    $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
	    $helper->identifier = $this->identifier;
	    $helper->submit_action = 'submit'.$this->name.'Module';
	    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false);
	    $helper->currentIndex .= '&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
	    $helper->token = \Tools::getAdminTokenLite('AdminModules');

	    $helper->tpl_vars = array(
	        'languages' => $this->context->controller->getLanguages(),
	        'id_language' => $this->context->language->id,
	        'fields_value' => array(
				'CB_ID_DOMAIN_GROUP' => Tools::getValue('CB_ID_DOMAIN_GROUP', Configuration::get('CB_ID_DOMAIN_GROUP')),
				'CB_MODE_MAINTENANCE' => Tools::getValue('CB_MODE_MAINTENANCE', Configuration::get('CB_MODE_MAINTENANCE')),
				'CB_ID_CMS_PAGE' => (int)Tools::getValue('CB_ID_CMS_PAGE', Configuration::get('CB_ID_CMS_PAGE'))
	        )
	    );

	    return $helper->generateForm(array(
			[
				'form' => [
					'legend' => [
						'title' => $this->l('Parameters'),
						'icon' => 'icon-cogs'
					],
					'input' => [
						[
						    'type' => 'text',
						    'name' => 'CB_ID_DOMAIN_GROUP',
						    'id' => 'CB_ID_DOMAIN_GROUP',
							'label' => $this->l('Group domain Id'),
							'desc' => $this->l('Group domain Id could be founded in your cookie bot account, section "Your scripts"'),
						    'required' => true,
						    'lang' => false,
						],
						[
						    'type' => 'select',
						    'name' => 'CB_ID_CMS_PAGE',
						    'id' => 'CB_ID_CMS_PAGE',
						    'label' => $this->l('Please select CMS page for cookie policy'),
							'desc' =>
								$this->l('This page will automatically include the cookie policy, you must make it available to your users (in the footer for example)').
								'<br>'.
								sprintf(
									$this->l('If you are using an existing page, include the following code to place the policy statement : %s'),
									htmlentities(self::COOKIE_STR_PLACEHOLDER)
								),
							'required' => true,
						    'options' => [
						        'default' => ['value' => null, 'label' => $this->l('Please select CMS page')],
						        'query' => \CMS::getCMSPages(\Context::getContext()->cookie->id_lang),
						        'id' => 'id_cms',
						        'name' => 'meta_title'
						    ]
						],
						[
							'type' => 'switch',
							'name' => 'CB_MODE_MAINTENANCE',
							'required' => true,
							'is_bool' => true,
							'label' => $this->l('Maintenance mode'),
							'desc' => $this->l('If enabled, only user logged in back-office will be able to see the banner'),
							'values' => [
								['id' => 'CB_MODE_MAINTENANCE_on', 'value' => 1, 'label' => $this->l('Yes')],
								['id' => 'CB_MODE_MAINTENANCE_off', 'value' => 0, 'label' => $this->l('No')],
							]
						]
					],
					'submit' => [
						'title' => $this->l('Save')
					]
				]
			]
	    ));
	}


	public function hookDisplayHeader() {
		if (
			!Configuration::get('CB_MODE_MAINTENANCE') ||
			(isset($this->context->employee) && Validate::isLoadedObject($this->context->employee))
		) {
			if (version_compare(_PS_VERSION_, '1.7.0', '>=')) {
				$this->context->controller->registerStylesheet('cookiebot-css', $this->getLocalPath() . '/views/css/cookiebot.css');
			} else {
				$this->context->controller->addCss($this->getLocalPath() . '/views/css/cookiebot.css');
			}
			$this->context->smarty->assign(array(
				'cb_group_domain_id' => Configuration::get('CB_ID_DOMAIN_GROUP')
			));
			return $this->display(__FILE__, 'views/templates/hook/header.tpl');
		}
	}

	public function hookDisplayFooter() {
		if (
			(
				!Configuration::get('CB_MODE_MAINTENANCE') ||
				(isset($this->context->employee) && Validate::isLoadedObject($this->context->employee))
			) &&
			(int)Tools::getValue('id_cms') === (int)Configuration::get('CB_ID_CMS_PAGE')
		) {
			$this->context->smarty->assign(array(
				'cb_group_domain_id' => Configuration::get('CB_ID_DOMAIN_GROUP')
			));
			return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
		}
	}
}