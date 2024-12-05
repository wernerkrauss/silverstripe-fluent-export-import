# Silverstripe Fluent Export / Import

An extension for silvertripe/fluent to export and import translations.

Run `dev/tasks/fluent-export` to export all translations as a zip of yml files.

Then you can translate the content and import it again (to be done).

## Important notice
I take no warranty for any data loss. Please backup your database before importing translations.

There is only a check if the yml file has the same locale as you plan to import. Using the import funcitonality will overwrite the related content in the database.

Please validate the yml files before importing them. Take care to escape apostrophs. 

## Configuration

You can configure fields you don't want to export, e.g. SiteTree's URLSegment:

```yml
SilverStripe\CMS\Model\SiteTree:
  translate_ignore:
    - URLSegment
```

## Automatic Translation
You can use the ChatGPT API to translate your content. To do so, you need to set the API key in your .env file:

```
CHATGPT_API_KEY=your-api-key
```

Next add extension to all classes you want to translate:

```yml
# SiteTree has already fluent applied
SilverStripe\CMS\Model\SiteTree:
  extensions:
    autotranslate: Netwerkstatt\FluentExIm\Extension\AutoTranslate

My\Namespace\Model\Foo:
    extensions:
        fluent: TractorCow\Fluent\Extension\FluentExtension
        autotranslate: Netwerkstatt\FluentExIm\Extension\AutoTranslat
# if you have configured translations, make sure to add IsAutoTranslated and LastTranslation manually
    translate:
        - IsAutoTranslated
        - LastTranslation
```

### fluent-ai-autotranslate task
When everything is configured properly you can run the task `dev/tasks/fluent-ai-autotranslate do_publish=1` to translate all content to the desired locale.

If IsAutoTranslated of LastTranslation is missing in localised fields, the task will throw a RuntimeException.

#### Parameters:
* `do_publish` (required): If set to 1, the task will publish the translated content.



⚠️ Be aware, that some extensions of other modules might add `translated` config to a class. 



## Todo
- [X] Export translations to YML
- [X] Import translations from YML
- [ ] Import translations from a zip containing yml files
- [ ] other file adapters like json or xliff
- [X] better UI, e.g. in LocaleAdmin
- [ ] documentation how to ask ChatGPT to translate in a correct way

## Thanks to
Thanks to [Nobrainer](https://www.nobrainer.dk/) and [Adiwidjaja Teamworks](https://www.adiwidjaja.com/) for sponsoring this module ❤️.

A special thank you to [Lekoala](https://github.com/lekoala) for the support with his CMS-Actions and PureModal modules.

Thanks to TractorCow and all contributors for the great [fluent module](https://github.com/tractorcow-farm/silverstripe-fluent). And thanks to the folks at Silverstripe for their great work.

## Need Help?

If you need some help with your Silverstripe project, feel free to [contact me](mailto:werner.krauss@netwerkstatt.at) ✉️.

See you at next [StripeCon](https://stripecon.eu) 👋
