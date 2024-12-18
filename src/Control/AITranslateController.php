<?php

namespace Netwerkstatt\FluentExIm\Control;

use Netwerkstatt\FluentExIm\Extension\AutoTranslate;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use TractorCow\Fluent\Model\Locale;

class AITranslateController extends Controller
{
    /**
     * @config
     */
    private static $allowed_actions = [
        'index',
        'TranslationForm',
        'doTranslate',
    ];

    /**
     * @config
     */
    private static $url_segment = 'aitranslate';

    protected function init()
    {
        parent::init();
        Requirements::css('silverstripe/admin: client/dist/styles/bundle.css');
    }


    public function index(HTTPRequest $request): string
    {
        return $this->renderDialog(['Form' => $this->TranslationForm($request)]);
    }

    public function TranslationForm(HTTPRequest $request): Form
    {
        $translatableLocales = Locale::get()->exclude(['IsGlobalDefault' => 1]);
        $localesCount = $translatableLocales->count();
        $localesString = implode(', ', $translatableLocales->column('Title'));
        $translateconfirmation = _t(
            self::class . '.TRANSLATE_CONFIRMATION',
            'Translate to 1 other locale ({locales})?|Translate to {count} other locales ({locales})?',
            ['count' => $localesCount, 'locales' => $localesString]
        );

        $fields = FieldList::create([
            LiteralField::create('TranslateDescription', $translateconfirmation),
            HiddenField::create('ClassName', 'ClassName', $request->requestVar('ClassName')),
            HiddenField::create('ID', 'ID', $request->requestVar('ID')),
            CheckboxField::create('doPublish', 'Publish after translation'),
            CheckboxField::create('forceTranslation', 'Force translation')
        ]);


        $translateAction = FormAction::create('doTranslate', _t(self::class . 'TRANSLATE', 'Translate'));
        $translateAction->addExtraClass('btn btn-outline-danger font-icon font-icon-upload');

        $actions = FieldList::create([
            $translateAction
        ]);

        return Form::create($this, 'TranslationForm', $fields, $actions);
    }


    public function doTranslate(array $data, Form $form)
    {
        $doPublish = $data['doPublish'] ?? false;
        $forceTranslation = $data['forceTranslation'] ?? false;
        //@todo ability to filter locales to translate to
        if (!array_key_exists('ID', $data) || !array_key_exists('ClassName', $data)) {
            $this->httpError(400, 'ID and ClassName required');
        }

        $object = $data['ClassName']::get()->byID($data['ID']);
        if (!$object) {
            $this->httpError(404);
        }

        if (!$object->hasExtension(AutoTranslate::class)) {
            $this->httpError(400, 'Object does not have AutoTranslate extension');
        }

        if (!$object->canTranslate()) {
            $this->httpError(403);
        }

//        Versioned::set_stage(Versioned::DRAFT);
        $status = $object->doRecursiveAutoTranslate($doPublish, $forceTranslation);

        $templates = SSViewer::get_templates_by_class(self::class, '_' . __FUNCTION__);

        return $this->customise(['Status' => ArrayList::create($status)])->renderWith($templates);
    }

    protected function renderDialog(array $customFields = null): string
    {
        // Set empty content by default otherwise it will render the full page
        if (empty($customFields['Content'])) {
            $customFields['Content'] = '';
        }

        return $this->renderWith('SilverStripe\\Admin\\CMSDialog', $customFields);
    }
}
