<?php
namespace Concrete\Package\ConfirmExpressFormPack\Block\ConfirmExpressForm;

use Concrete\Controller\Element\Attribute\KeyList;
use Concrete\Controller\Element\Dashboard\Express\Control\TextOptions;
use Concrete\Core\Attribute\Category\ExpressCategory;
use Concrete\Core\Attribute\Context\AttributeTypeSettingsContext;
use Concrete\Core\Attribute\Type;
use Concrete\Core\Block\BlockController;
use Concrete\Core\Entity\Attribute\Key\ExpressKey;
use Concrete\Core\Entity\Express\Control\AttributeKeyControl;
use Concrete\Core\Entity\Express\Control\TextControl;
use Concrete\Core\Entity\Express\Entity;
use Concrete\Core\Entity\Express\Entry;
use Concrete\Core\Entity\Express\FieldSet;
use Concrete\Core\Entity\Express\Form;
use Concrete\Core\Express\Attribute\AttributeKeyHandleGenerator;
use Concrete\Core\Express\Controller\ControllerInterface;
use Concrete\Core\Express\Entry\Notifier\Notification\FormBlockSubmissionEmailNotification;
use Concrete\Core\Express\Entry\Notifier\Notification\FormBlockSubmissionNotification;
use Concrete\Core\Express\Entry\Notifier\NotificationInterface;
use Concrete\Core\Express\Entry\Notifier\NotificationProviderInterface;
use Concrete\Core\Express\Form\Context\FrontendFormContext;
use Concrete\Core\Express\Form\Control\Type\EntityPropertyType;
use Concrete\Core\Express\Form\Control\SaveHandler\SaveHandlerInterface;
use Concrete\Core\Express\Form\Processor\ProcessorInterface;
use Concrete\Core\Express\Form\Validator\Routine\CaptchaRoutine;
use Concrete\Core\Express\Form\Validator\ValidatorInterface;
use Concrete\Core\Express\Generator\EntityHandleGenerator;
use Concrete\Core\File\FileProviderInterface;
use Concrete\Core\File\Filesystem;
use Concrete\Core\File\Set\Set;
use Concrete\Core\Form\Context\ContextFactory;
use Concrete\Core\Http\ResponseAssetGroup;
use Concrete\Core\Routing\Redirect;
use Concrete\Core\Support\Facade\Express;
use Concrete\Core\Tree\Node\Node;
use Concrete\Core\Tree\Node\NodeType;
use Concrete\Core\Tree\Node\Type\Category;
use Concrete\Core\Tree\Node\Type\ExpressEntryCategory;
use Concrete\Core\Tree\Type\ExpressEntryResults;
use Doctrine\ORM\Id\UuidGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;

use Core;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Concrete\Core\Express\Event\Event;
use Concrete\Core\Entity\Express\Control\Control;
use Concrete\Core\Entity\Attribute\Value\Value\ImageFileValue;
use Concrete\Core\File\Importer;

class Controller extends BlockController
{
    protected $btInterfaceWidth = 640;
    protected $btCacheBlockOutput = false;
    protected $btInterfaceHeight = 480;
    protected $btTable = 'btConfirmExpressForm';
    protected $entityManager;
    protected $form_session = null;
    protected $session_name;

    const FORM_RESULTS_CATEGORY_NAME = 'Forms';

    /**
     * Used for localization. If we want to localize the name/description we have to include this.
     *
     * @return string
     */
    public function getBlockTypeDescription()
    {
        return t("Build simple forms and surveys.");
    }

    public function getBlockTypeName()
    {
        return t("Confirm Form");
    }

    protected function clearSessionControls()
    {
        $session = \Core::make('session');
        $session->remove('block.confirm_express_form.new');
    }

    public function add()
    {
        $c = \Page::getCurrentPage();
        $this->set('formName', $c->getCollectionName());
        $this->set('submitLabel', t('Submit'));
        $this->set('thankyouMsg', t('Thanks!'));
        $this->edit();
        $this->set('resultsFolder', $this->get('formResultsRootFolderNodeID'));
    }

    public function action_form_success($bID = null)
    {
        if ($this->bID == $bID) {
            $this->set('success', $this->thankyouMsg);
            $this->view();
        }
    }

    public function delete()
    {
        parent::delete();
        $entity = $this->getFormEntity()->getEntity();
        $entityManager = \Core::make('database/orm')->entityManager();
        // Important – are other blocks in the system using this form? If so, we don't want to delete it!
        $db = $entityManager->getConnection();
        $r = $db->fetchColumn('select count(bID) from btExpressForm where bID <> ? and exFormID = ?', [$this->bID, $this->exFormID]);
        if ($r == 0) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
    }

    public function action_submit($bID = null)
    {
        if ($this->bID == $bID) {
            $entityManager = \Core::make('database/orm')->entityManager();
            $form = $this->getFormEntity();
            if (is_object($form)) {
                $express = \Core::make('express');
                $entity = $form->getEntity();
                /**
                 * @var $controller ControllerInterface
                 */
                $controller = $express->getEntityController($entity);
                $processor = $controller->getFormProcessor();
                $validator = $processor->getValidator($this->request);
                if ($this->displayCaptcha) {
                    $validator->addRoutine(new CaptchaRoutine(\Core::make('helper/validation/captcha')));
                }

                $validator->validate($form, ProcessorInterface::REQUEST_TYPE_ADD);

                $e = $validator->getErrorList();

                $this->set('error', $e);
            }

            $entity = $form->getEntity();
            $permissions = new \Permissions($entity);
            if (!$permissions->canAddExpressEntries()) {
                $e->add(t('You do not have access to submit this form.'));
            }
            /* ===============================================
            requestとsessionの準備
            =============================================== */
            $request = \Request::getInstance();
            $this->form_session = \Core::make('session');
            $session_name = $this->get_form_session_name();
            /* ===============================================
            確認画面
            =============================================== */
            if(isset($e) && !$e->has() && $request->get('mode') == 'confirm' && $request->getMethod() == 'POST'){
                $requestArray = $request->request->all();
                $akID =  $requestArray['akID'];
                foreach($akID as $key => $val){
                    foreach($val as $k => $v){
                        if($k == 'atSelectOptionValue'){
                            if(is_array($v)){
                                $v = implode(',', $v);
                            }
                            $akID[$key][$k] = $v;
                        }
                    }
                }
                /* 一次保存用ファイル
                ----------------------- */
                $this->create_tmp_dir();
                /* 一時ファイルを削除
                ----------------------- */
                $this->clean_tmp_dir();
                /* htmlファイルアップローダーは処理を分ける
                ----------------------- */
                if(is_object($request->files)){
                    $files = $request->files->get('akID');
                    if($files){
                        foreach($files as $ak_id => $ak_file){
                            $file_data = $ak_file['value'];
                            if(!$file_data || !file_exists($file_data->getPathname())) continue;

                            $path = md5_file($file_data->getPathname()) . '.' . $file_data->guessExtension();

                            $akID[$ak_id]['file']['tmp_name'] = $this->get_tmp_dir().$path;
                            $akID[$ak_id]['file']['origin_name'] = $file_data->getClientOriginalName();
                            $akID[$ak_id]['file']['src'] = DIR_REL.str_replace(DIR_BASE, '',$this->get_tmp_dir().$path);
                            $akID[$ak_id]['file']['extention'] = $file_data->guessExtension();
                            $file_data->move($this->get_tmp_dir(), $path);
                        }
                    }
                }
                $requestArray['akID'] = $akID;
                $request->request->replace($requestArray);
                $this->set_form_sessions();
            }

             /* ===============================================
            送信
            =============================================== */
            if (isset($e) && !$e->has() && $request->get('mode') == 'send') {
                $manager = $controller->getEntryManager($this->request);
                $entry = $manager->addEntry($entity);
                /* htmlファイルアップローダーは処理を分ける
                ----------------------- */
                foreach ($form->getControls() as $control) {
                    $type = $control->getControlType();
                    $key = $control->getAttributeKey();
                    $settings = $key->getController()->getAttributeKeySettings();
                    $handle = $key->getAttributeTypeHandle();

                    if($handle === 'image_file' && $settings->isModeHtmlInput()){
                        //確認画面からのファイル送信は直接保存
                        $value = $this->createImgAttributeValueFromRequest($key);
                        if($value->getFileID() > 0){
                            $entry->setAttribute($key, $value);
                        }
                    }else{
                         $saver = $type->getSaveHandler($control);
                        if ($saver instanceof SaveHandlerInterface) {
                            $saver->saveFromRequest($control, $entry, $this->request);
                        }
                    }
                }
                /* ===============================================
                ▼▼▼flushの意味？？？だれか教えて▼▼▼
                =============================================== */
                $entityManager->flush();

                $ev = new Event($entry);
                $ev->setEntityManager($this->entityManager);
                \Events::dispatch('on_express_entry_saved', $ev);
                /* ===============================================
                ▲▲▲flush掛ける意味？？？▲▲▲
                =============================================== */
                $entry = $ev->getEntry();
                $values = $entity->getAttributeKeyCategory()->getAttributeValues($entry);

                // Check antispam
                $antispam = \Core::make('helper/validation/antispam');
                $submittedData = '';
                foreach($values as $value) {
                    $submittedData .= $value->getAttributeKey()->getAttributeKeyDisplayName() . ":\r\n";
                    $submittedData .= $value->getPlainTextValue() . "\r\n\r\n";
                }

                if (!$antispam->check($submittedData, 'form_block')) {
                    // Remove the entry and silently fail.
                    $entityManager->refresh($entry);
                    $entityManager->remove($entry);
                    $entityManager->flush();
                    $c = \Page::getCurrentPage();
                    $r = Redirect::page($c);
                    $r->setTargetUrl($r->getTargetUrl() . '#form' . $this->bID);
                    return $r;
                }

                if ($this->addFilesToSet) {
                    $set = Set::getByID($this->addFilesToSet);
                    if (is_object($set)) {
                        foreach($values as $value) {
                            $value = $value->getValueObject();
                            if ($value instanceof FileProviderInterface) {
                                $files = $value->getFileObjects();
                                foreach($files as $file) {
                                    $set->addFileToSet($file);
                                }
                            }
                        }
                    }
                }
                if ($this->notifyMeOnSubmission) {
                    if (\Config::get('concrete.email.form_block.address') && strstr(\Config::get('concrete.email.form_block.address'), '@')) {
                        $formFormEmailAddress = \Config::get('concrete.email.form_block.address');
                    } else {
                        $adminUserInfo = \UserInfo::getByID(USER_SUPER_ID);
                        $formFormEmailAddress = $adminUserInfo->getUserEmail();
                    }

                    $replyToEmailAddress = $formFormEmailAddress;
                    $l = new Logger('My-Channel');             // You can filter logs by channel

        // To add a custom message
        
                    if ($this->replyToEmailControlID) {
                        $control = $entityManager->getRepository('Concrete\Core\Entity\Express\Control\Control')
                            ->findOneById($this->replyToEmailControlID);
                        if (is_object($control)) {
                            $ev_repo = $entityManager->getRepository('\Concrete\Core\Entity\Attribute\Value\ExpressValue');
                            $ev_target = $ev_repo->findOneBy([
                                'entry' => $entry,
                                'attribute_key' => $control->getAttributeKey(),
                            ]);
                            //$email = $entry->getAttributeValues($control->getAttributeKey());
                            $email = $ev_target->getValue();
                            if ($email) {
                                $replyToEmailAddress = $email;
                            }
                        }
                    }

                    $formName = $this->getFormEntity()->getEntity()->getName();
                    $bo = $this->getBlockObject();
                    $pkgHandle = $bo->getPackageHandle();
                    /* ===============================================
                    メール用パラメータ
                    =============================================== */
                    //$fromAddressToUser = $this->fromAddressToUser;
                    $fromNameToUser = $this->fromNameToUser;
                    $subjectAdmin = $this->subjectAdmin;
                    $subjectUser = $this->subjectUser;
                    $preambleAdmin = $this->preambleAdmin;
                    $signatureAdmin = $this->signatureAdmin;
                    $preambleUser = $this->preambleUser;
                    $signatureUser = $this->signatureUser;
                    /* ===============================================
                    管理者宛
                    =============================================== */
                    $mh = \Core::make('helper/mail');
                    $mh->to($this->recipientEmail);
                    $mh->from($replyToEmailAddress);
                    $mh->replyto($replyToEmailAddress);
                    $mh->addParameter('entity', $entity);
                    $mh->addParameter('formName', $formName);
                    $mh->addParameter('attributes', $values);
                    $mh->addParameter('preambleAdmin', $preambleAdmin);
                    $mh->addParameter('signatureAdmin', $signatureAdmin);
                    $mh->load('block_express_form_submission_admin',$pkgHandle);
                    if($subjectAdmin){
                        $mh->setSubject(t($subjectAdmin));
                    }else{
                        $mh->setSubject(t('Website Form Submission – %s', $formName));
                    }

                    $mh->sendMail();
                    /* ===============================================
                    ユーザー向け
                    =============================================== */
                    $mh = null;
                    $mh = \Core::make('helper/mail');
                    $mh->to($replyToEmailAddress);
                    // if($fromAddressToUser && $fromNameToUser){
                    //     $mh->from($fromAddressToUser,$fromNameToUser);
                    // }else{
                    //     $mh->from($this->recipientEmail);
                    // }
                    $mh->addParameter('entity', $entity);
                    $mh->addParameter('formName', $formName);
                    $mh->addParameter('attributes', $values);
                    $mh->addParameter('preambleUser', $preambleUser);
                    $mh->addParameter('signatureUser', $signatureUser);
                    $mh->load('block_express_form_submission_user',$pkgHandle);

                    if($subjectUser){
                        $mh->setSubject($subjectUser);
                    }else{
                        $mh->setSubject(t('Website Form Submission – %s', $formName));
                    }

                    $mh->sendMail();
                }
                /* sessionを破棄
                ----------------------- */
                $this->form_session->remove($session_name);
                /* 一時ファイルを削除
                ----------------------- */
                $this->clean_tmp_dir();
                /* 送信後の画面処理
                ----------------------- */
                if ($this->redirectCID > 0) {
                    $c = \Page::getByID($this->redirectCID);
                    if (is_object($c) && !$c->isError()) {
                        $r = Redirect::page($c);
                        $r->setTargetUrl($r->getTargetUrl() . '?form_success=1');
                        return $r;
                    }
                }

                $c = \Page::getCurrentPage();
                $url = \URL::to($c, 'form_success', $this->bID);
                $r = Redirect::to($url);
                $r->setTargetUrl($r->getTargetUrl() . '#form' . $this->bID);
                return $r;

            }
        }
        $this->view();
    }

    protected function loadResultsFolderInformation()
    {
        $folder = ExpressEntryCategory::getNodeByName(self::FORM_RESULTS_CATEGORY_NAME);
        $this->set('formResultsRootFolderNodeID', $folder->getTreeNodeID());
    }

    public function action_add_control()
    {
        $entityManager = \Core::make('database/orm')->entityManager();
        $post = $this->request->request->all();
        $session = \Core::make('session');
        $controls = $session->get('block.confirm_express_form.new');

        if (!is_array($controls)) {
            $controls = array();
        }

        $field = explode('|', $this->request->request->get('type'));
        switch($field[0]) {
            case 'attribute_key':
                $type = Type::getByID($field[1]);
                if (is_object($type)) {

                    $control = new AttributeKeyControl();
                    $control->setId((new UuidGenerator())->generate($entityManager, $control));
                    $key = new ExpressKey();
                    $key->setAttributeKeyName($post['question']);
                    if ($post['required']) {
                        $control->setIsRequired(true);
                    }
                    $key->setAttributeType($type);
                    if (!$post['question']) {
                        $e = \Core::make('error');
                        $e->add(t('You must give this question a name.'));
                        return new JsonResponse($e);
                    }
                    $controller = $type->getController();
                    $key = $this->saveAttributeKeySettings($controller, $key, $post);

                    $control->setAttributeKey($key);
                }
                break;
            case 'entity_property':
                /**
                 * @var $propertyType EntityPropertyType
                 */
                $propertyType = $this->app->make(EntityPropertyType::class);
                $control = $propertyType->createControlByIdentifier($field[1]);
                $control->setId((new UuidGenerator())->generate($entityManager, $control));
                $saver = $control->getControlSaveHandler();
                $control = $saver->saveFromRequest($control, $this->request);
                break;
        }

        if (!isset($control)) {
            $e = \Core::make('error');
            $e->add(t('You must choose a valid field type.'));
            return new JsonResponse($e);
        } else {
            $controls[$control->getId()]= $control;
            $session->set('block.confirm_express_form.new', $controls);
            return new JsonResponse($control);
        }
    }

    protected function saveAttributeKeySettings($controller, ExpressKey $key, $post)
    {
        $settings = $controller->saveKey($post);
        if (!is_object($settings)) {
            $settings = $controller->getAttributeKeySettings();
        }
        $settings->setAttributeKey($key);
        $key->setAttributeKeySettings($settings);
        return $key;
    }

    public function action_update_control()
    {
        $entityManager = \Core::make('database/orm')->entityManager();
        $post = $this->request->request->all();
        $session = \Core::make('session');

        $sessionControls = $session->get('block.confirm_express_form.new');
        if (is_array($sessionControls)) {
            foreach($sessionControls as $sessionControl) {
                if ($sessionControl->getId() == $this->request->request->get('id')) {
                    $control = $sessionControl;
                    break;
                }
            }
        }

        if (!isset($control)) {
            $control = $entityManager->getRepository('Concrete\Core\Entity\Express\Control\Control')
                ->findOneById($this->request->request->get('id'));
        }

        $field = explode('|', $this->request->request->get('type'));
        switch($field[0]) {
            case 'attribute_key':
                $type = Type::getByID($field[1]);
                if (is_object($type)) {
                    $key = $control->getAttributeKey();
                    $key->setAttributeKeyName($post['question']);
                    if (!$post['question']) {
                        $e = \Core::make('error');
                        $e->add(t('You must give this question a name.'));
                        return new JsonResponse($e);
                    }
                    if ($post['requiredEdit']) {
                        $control->setIsRequired(true);
                    } else {
                        $control->setIsRequired(false);
                    }
                    $controller = $key->getController();
                    $key = $this->saveAttributeKeySettings($controller, $key, $post);
                    $control->setAttributeKey($key);
                }
                break;
            case 'entity_property':
                /**
                 * @var $propertyType EntityPropertyType
                 */
                $type = $this->app->make(EntityPropertyType::class);
                $saver = $control->getControlSaveHandler();
                $control = $saver->saveFromRequest($control, $this->request);
                break;
        }

        if (!is_object($type)) {
            $e = \Core::make('error');
            $e->add(t('You must choose a valid field type.'));
            return new JsonResponse($e);
        } else {
            $sessionControls[$control->getId()]= $control;
            $session->set('block.confirm_express_form.new', $sessionControls);
            return new JsonResponse($control);
        }

    }

    public function save($data)
    {
        if (isset($data['exFormID'])) {
            return parent::save($data);
        }
        $requestControls = (array) $this->request->request->get('controlID');
        $entityManager = \Core::make('database/orm')->entityManager();
        $session = \Core::make('session');
        $sessionControls = $session->get('block.confirm_express_form.new');

        if (!$this->exFormID) {

            // This is a new submission.
            $c = \Page::getCurrentPage();
            $name = $data['formName'] ? $data['formName'] : t('Form');

            // Create a results node
            $node = ExpressEntryCategory::getNodeByName(self::FORM_RESULTS_CATEGORY_NAME);
            $node = \Concrete\Core\Tree\Node\Type\ExpressEntryResults::add($name, $node);

            $entity = new Entity();
            $entity->setName($name);
            $entity->setIncludeInPublicList(false);
            $generator = new EntityHandleGenerator($entityManager);
            $entity->setHandle($generator->generate($entity));
            $entity->setEntityResultsNodeId($node->getTreeNodeID());
            $entityManager->persist($entity);
            $entityManager->flush();

            $form = new Form();
            $form->setName(t('Form'));
            $form->setEntity($entity);
            $entity->setDefaultViewForm($form);
            $entity->setDefaultEditForm($form);
            $entityManager->persist($form);
            $entityManager->flush();

            // Create a Field Set and a Form
            $field_set = new FieldSet();
            $field_set->setForm($form);
            $entityManager->persist($field_set);
            $entityManager->flush();

            $indexer = $entity->getAttributeKeyCategory()->getSearchIndexer();
            if (is_object($indexer)) {
                $indexer->createRepository($entity->getAttributeKeyCategory());
            }

        } else {
            // We check save the order as well as potentially deleting orphaned controls.
            $form = $entityManager->getRepository('Concrete\Core\Entity\Express\Form')
                ->findOneById($this->exFormID);

            /**
             * @var $form Form
             * @var $field_set FieldSet
             */
            $field_set = $form->getFieldSets()[0];
            $entity = $form->getEntity();
        }

        $attributeKeyCategory = $entity->getAttributeKeyCategory();

        // First, we get the existing controls, so we can check them to see if controls should be removed later.
        $existingControls = $form->getControls();
        $existingControlIDs = array();
        foreach($existingControls as $control) {
            $existingControlIDs[] = $control->getId();
        }

        // Now, let's loop through our request controls
        $indexKeys = array();
        $position = 0;

        foreach($requestControls as $id) {

            if (isset($sessionControls[$id])) {
                $control = $sessionControls[$id];
                if (!in_array($id, $existingControlIDs)) {
                    // Possibility 1: This is a new control.
                    if ($control instanceof AttributeKeyControl) {
                        $key = $control->getAttributeKey();
                        $type = $key->getAttributeType();
                        $settings = $key->getAttributeKeySettings();

                        // We have to merge entities back into the entity manager because they have been
                        // serialized. First type, because if we merge key first type gets screwed
                        $type = $entityManager->merge($type);

                        // Now key, because we need key to set as the primary key for settings.
                        $key = $entityManager->merge($key);
                        $key->setAttributeType($type);
                        $key->setEntity($entity);
                        $key->setAttributeKeyHandle((new AttributeKeyHandleGenerator($attributeKeyCategory))->generate($key));
                        $entityManager->persist($key);
                        $entityManager->flush();

                        // Now attribute settings.
                        $settings->setAttributeKey($key);
                        $settings = $entityManager->merge($settings);
                        $entityManager->persist($settings);
                        $entityManager->flush();

                        $control->setAttributeKey($key);
                        $indexKeys[] = $key;
                    }

                    $control->setFieldSet($field_set);
                    $control->setPosition($position);
                    $entityManager->persist($control);
                    $entityManager->flush();


                } else {
                    // Possibility 2: This is an existing control that has an updated version.
                    foreach($existingControls as $existingControl) {
                        if ($existingControl->getId() == $id) {
                            if ($control instanceof AttributeKeyControl) {
                                $settings = $control->getAttributeKey()->getAttributeKeySettings();
                                $key = $existingControl->getAttributeKey();
                                $type = $key->getAttributeType();
                                $type = $entityManager->merge($type);

                                // question name
                                $key->setAttributeKeyName($control->getAttributeKey()->getAttributeKeyName());
                                $key->setAttributeKeyHandle((new AttributeKeyHandleGenerator($attributeKeyCategory))->generate($key));

                                // Key Type
                                $key = $entityManager->merge($key);
                                $key->setAttributeType($type);

                                $type = $control->getAttributeKey()->getAttributeType();
                                $type = $entityManager->merge($type);
                                $key->setAttributeType($type);
                                $settings = $control->getAttributeKey()->getAttributeKeySettings();
                                $settings->setAttributeKey($key);
                                $settings = $settings->mergeAndPersist($entityManager);

                                // Required
                                $existingControl->setIsRequired($control->isRequired());

                                // Finalize control
                                $existingControl->setAttributeKey($key);

                                $indexKeys[] = $key;
                            } else if ($control instanceof TextControl) {
                                // Wish we had a better way of doing this that wasn't so hacky.
                                $existingControl->setHeadline($control->getHeadline());
                                $existingControl->setBody($control->getBody());
                            }

                            // save it.
                            $entityManager->persist($existingControl);

                        }
                    }
                }
            } else {
                // Possibility 3: This is an existing control that doesn't have a new version. But we still
                // want to update its position.
                foreach($existingControls as $control) {
                    if ($control->getId() == $id) {
                        $control->setPosition($position);
                        $entityManager->persist($control);
                    }
                }
            }

            $position++;
        }

        // Now, we look through all existing controls to see whether they should be removed.
        foreach($existingControls as $control) {
            // Does this control exist in the request? If not, it gets axed
            if (!is_array($requestControls) || !in_array($control->getId(), $requestControls)) {
                $entityManager->remove($control);
            }
        }

        $entityManager->flush();

        $category = new ExpressCategory($entity, \Core::make('app'), $entityManager);
        $indexer = $category->getSearchIndexer();
        foreach($indexKeys as $key) {
            $indexer->updateRepositoryColumns($category, $key);
        }

        // Now, we handle the entity results folder.
        $resultsNode = Node::getByID($entity->getEntityResultsNodeId());
        $folder = Node::getByID($data['resultsFolder']);
        if (is_object($folder)) {
            $resultsNode->move($folder);
        }


        $data['exFormID'] = $form->getId();

        $this->clearSessionControls();
        parent::save($data);
    }

    public function edit()
    {
        $this->loadResultsFolderInformation();
        $this->requireAsset('core/tree');
        $this->clearSessionControls();
        $list = Type::getList();

        $attribute_fields = array();

        foreach($list as $type) {
            $attribute_fields[] = ['id' => 'attribute_key|' . $type->getAttributeTypeID(), 'displayName' => $type->getAttributeTypeDisplayName()];
        }

        $select = array();
        $select[0] = new \stdClass();
        $select[0]->label = t('Input Field Types');
        $select[0]->fields = $attribute_fields;

        $other_fields = array();
        $other_fields[] = ['id' => 'entity_property|text', 'displayName' => t('Display Text')];

        $select[1] = new \stdClass();
        $select[1]->label = t('Other Fields');
        $select[1]->fields = $other_fields;

        $controls = array();
        $form = $this->getFormEntity();
        if (is_object($form)) {
            $entity = $form->getEntity();
            $controls = $form->getControls();
            $this->set('formName', $entity->getName());
            $this->set('submitLabel', $this->submitLabel);
            $node = Node::getByID($entity->getEntityResultsNodeId());
            if (is_object($node)) {
                $folder = $node->getTreeNodeParentObject();
                $this->set('resultsFolder', $folder->getTreeNodeID());
            }
        }
        $this->set('controls', $controls);
        $this->set('types_select', $select);
        $tree = ExpressEntryResults::get();
        $this->set('tree', $tree);
    }

    public function action_get_type_form()
    {
        $field = explode('|', $this->request->request->get('id'));
        if ($field[0] == 'attribute_key') {
            $type = Type::getByID($field[1]);
            if (is_object($type)) {
                ob_start();
                echo $type->render(new AttributeTypeSettingsContext());
                $html = ob_get_contents();
                ob_end_clean();

                $obj = new \stdClass();
                $obj->content = $html;
                $obj->showControlRequired = true;
                $obj->showControlName = true;
                $obj->assets = $this->getAssetsDefinedDuringOutput();

            }
        } else if ($field[0] == 'entity_property') {
            $obj = new \stdClass();
            switch($field[1]) {
                case 'text':
                    $controller = new TextOptions();
                    ob_start();
                    echo $controller->render();
                    $html = ob_get_contents();
                    ob_end_clean();

                    $obj = new \stdClass();
                    $obj->content = $html;
                    $obj->showControlRequired = false;
                    $obj->showControlName = false;
                    $obj->assets = $this->getAssetsDefinedDuringOutput();
                    break;
            }
        }
        if (isset($obj)) {
            return new JsonResponse($obj);
        }
        \Core::make('app')->shutdown();
    }

    protected function getAssetsDefinedDuringOutput()
    {
        $ag = ResponseAssetGroup::get();
        $r = array();
        foreach ($ag->getAssetsToOutput() as $position => $assets) {
            foreach ($assets as $asset) {
                if (is_object($asset)) {
                    $r[$asset->getAssetType()][] = $asset->getAssetURL();
                }
            }
        }
        return $r;
    }

    public function action_get_control()
    {
        $entityManager = \Core::make('database/orm')->entityManager();
        $session = \Core::make('session');
        $sessionControls = $session->get('block.confirm_express_form.new');
        if (is_array($sessionControls)) {
            foreach($sessionControls as $sessionControl) {
                if ($sessionControl->getID() == $this->request->query->get('control')) {
                    $control = $sessionControl;
                    break;
                }
            }
        }

        if (!isset($control)) {
            $control = $entityManager->getRepository('Concrete\Core\Entity\Express\Control\Control')
                ->findOneById($this->request->query->get('control'));
        }

        if (is_object($control)) {

            $obj = new \stdClass();

            if ($control instanceof AttributeKeyControl) {
                $type = $control->getAttributeKey()->getAttributeType();
                ob_start();
                echo $type->render(new AttributeTypeSettingsContext(), $control->getAttributeKey());
                $html = ob_get_contents();
                ob_end_clean();

                $obj->question = $control->getDisplayLabel();
                $obj->isRequired = $control->isRequired();
                $obj->showControlRequired = true;
                $obj->showControlName = true;
                $obj->type = 'attribute_key|' . $type->getAttributeTypeID();
                $obj->typeDisplayName = $type->getAttributeTypeDisplayName();
            } else {

                $controller = $control->getControlOptionsController();
                ob_start();
                echo $controller->render();
                $html = ob_get_contents();
                ob_end_clean();

                $obj->showControlRequired = false;
                $obj->showControlName = false;
                $obj->type = 'entity_property|text';
            }

            $obj->id = $control->getID();
            $obj->assets = $this->getAssetsDefinedDuringOutput();
            $obj->typeContent = $html;
            return new JsonResponse($obj);
        }
        \Core::make('app')->shutdown();
    }

    protected function getFormEntity()
    {
        $entityManager = \Core::make('database/orm')->entityManager();
        return $entityManager->getRepository('Concrete\Core\Entity\Express\Form')
            ->findOneById($this->exFormID);
    }
    public function view()
    {
        $form = $this->getFormEntity();
         if ($form) {
            $entity = $form->getEntity();
            if ($entity) {
                $express = \Core::make('express');
                $controller = $express->getEntityController($entity);
                $factory = new ContextFactory($controller);
                $context = $factory->getContext(new FrontendFormContext());
                $renderer = new \Concrete\Core\Express\Form\Renderer(
                    $context,
                    $form
                );
                if (is_object($form)) {
                    $this->set('expressForm', $form);
                }
                if ($this->displayCaptcha) {
                    $this->requireAsset('css', 'core/frontend/captcha');
                }
                $this->requireAsset('css', 'core/frontend/errors');
                $this->set('renderer', $renderer);

                /* ===============================================
                フォーム情報ををバラで取得する
                =============================================== */
                //各項目
                $af = Core::make('helper/form/attribute');
                $entity = $form->getEntity();
                $controls = $form->getControls();
                $controlSet = [];
                foreach($controls as $control){
                    $value = $af->setAttributeObject($control);
                    $key = $control->getAttributeKey();
                    $type = $key->getAttributeType();
                    $handle = $key->getAttributeTypeHandle();

                    $obj = new \stdClass();
                    $obj->question = $control->getDisplayLabel();
                    $obj->isRequired = $control->isRequired();
                    $obj->showControlRequired = true;
                    $obj->showControlName = true;
                    $obj->type = 'attribute_key|' . $type->getAttributeTypeID();
                    $obj->typeDisplayName = $type->getAttributeTypeDisplayName();
                    $obj->controlID = $control->getID();
                    $obj->keyID = $key->getAttributeKeyID();
                    $obj->typeContent = $key->render('composer', $value, true);

                    //select系属性はオプションとオプションIDももらう。
                    $options = [];
                    $akc = $key->getController();
                    if($handle == 'select'){
                        $optionList = $akc->getOptions();
                        if($optionList){
                            foreach($optionList as $option){
                                $options[$option->getSelectAttributeOptionID()] = $option->getSelectAttributeOptionDisplayValue();
                            }
                            $obj->hasOption = true;
                            $obj->options = $options;
                        }else{
                            $obj->hasOption = false;
                        }
                    }

                    $controlSet[] = $obj;
                }

                $this->set('form_controls',$controlSet);
                $token = Core::make('token');
                $utilities['ccm_token'] = $token->generate('express_form');
                $utilities['express_form_id'] = $form->getID();
                $this->set('form_utilities',$utilities);
                $this->set('form_id',$form->getID());

                $this->form_session = \Core::make('session');
                $this->set_form_sessions();
            }
        }
        if (!isset($renderer)) {
            $page = $this->block->getBlockCollectionObject();
            $this->app->make('log')
                ->warning(t('Form block on page %s (ID: %s) could not be loaded. Its express object or express form no longer exists.', $page->getCollectionName(), $page->getCollectionID()));
        }
    }

    /**
     * このフォームで参照するセッション名
     *
     * @return string セッション名
     */
    private function get_form_session_name(){
        $name = 'express_form_id.'.$this->getFormEntity()->getID();
        return $name;
    }
     /**
     * viewにsession情報を渡す
     *
     */
    private function set_form_sessions(){
        $request = \Request::getInstance();
        $session_name = $this->get_form_session_name();
        if('POST' == $request->getMethod()){
            $this->form_session->set($session_name, $request->request);
        }

        $form_sessions = $this->form_session->get($session_name);
        if($form_sessions){
            $form_session_data = $form_sessions->get('akID');
            $this->set('sessions_id' , $form_sessions->get('express_form_id'));
            $this->set('session_name' , $session_name);
            $this->set('form_session_data' , $form_session_data);
            $this->set('form_session_data_json' , json_encode($form_session_data));
            $this->set('mode' , $this->request->get('mode'));
            $this->set('form_id' , $this->getFormEntity()->getID());
        }
    }

    /**
     * Temp ディレクトリ名（パス、URL）を返す。ディレクトリの存在可否は関係なし
     *
     * @return string Tempディレクトリのパス
     */
    private function get_tmp_dir() {
       return dirname(__FILE__).'/tmp/';
    }

    /**
     * Temp ディレクトリを作成
     *
     * @return bool
     */
    private function create_tmp_dir() {
        $_ret = false;
        $temp_dir = $this->get_tmp_dir();
        if ( !file_exists( $temp_dir ) && !is_writable( $temp_dir ) ) {
            $_ret = mkdir( $temp_dir , 0733 , TRUE);
            return $_ret;
        }
        return $_ret;
    }

    /**
     * 一時保存ディクトリを空にする 一次保存されてから5分を超えた画像は削除
     */
    private function clean_tmp_dir() {
        $temp_dir = $this->get_tmp_dir();
        if ( !file_exists( $temp_dir ) ) {
            return;
        }
        $handle = opendir( $temp_dir );
        if ( $handle === false ) {
            return;
        }
        while ( false !== ( $filename = readdir( $handle ) ) ) {
            if ( $filename !== '.' && $filename !== '..' &&
                 !is_dir( rtrim( $temp_dir, '/\\' ). '/' . $filename ) ) {
                $stat = stat( rtrim( $temp_dir, '/\\' ). '/' . $filename );
                if ( $stat['mtime'] + 300 < time() ) {
                    unlink( rtrim( $temp_dir, '/\\' ). '/' . $filename );
                }
            }
        }
        closedir( $handle );
    }

    /**
     * ExpressKeyインスタンスから画像をファイルマネージャへ登録する
     * @param ExpressKey $key
     * @return object
     */
    protected function createImgAttributeValueFromRequest(ExpressKey $key)
    {
        $form_controller = $key->getController();
        $form_controller_id = $key->getAttributeKeyID();
        if ($form_controller->getAttributeKeySettings()->isModeHtmlInput()) {
            $tmp_name = $_POST['akID'][$form_controller_id]['value']['tmp_name'];
            $name = $_POST['akID'][$form_controller_id]['value']['origin_name'];
            if (!empty($tmp_name)) {
                $importer = new Importer();
                $f = $importer->import($tmp_name, $name);
                if (is_object($f)) {
                    $value = new ImageFileValue();
                    $value->setFileObject($f->getFile());
                    return $value;
                }
            }
        }
        return null;
    }
}
