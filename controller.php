<?php
namespace Concrete\Package\ConfirmExpressFormPack;
defined('C5_EXECUTE') or die("Access Denied.");

use Package;
use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Block\BlockType\Set as BlockTypeSet;


class Controller extends Package
{

	protected $pkgDescription = "Base utilies and snipetts";
	protected $pkgName = "Confirm Express Form Pack";
	protected $pkgHandle = 'confirm_express_form_pack';
	protected $appVersionRequired = '5.8.0.1';
	protected $pkgVersion = '1.0';

	public function install(){
		$pkg = parent::install();

		$ci = new ContentImporter();
		$ci->importContentFile($pkg->getPackagePath() . '/config/install.xml');

		$bt = BlockType::getByHandle('confirm_express_form');
		$btSet = BlockTypeSet::getByHandle('form');
		if (is_object($bt) && is_object($btSet)) {
			$btSet->addBlockType($bt);
		}
	}

}
