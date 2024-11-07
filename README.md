# Silverstripe Fluent Export / Import

An extension for silvertripe/fluent to export and import translations.

Run `dev/tasks/fluent-export` to export all translations as a zip of yml files.

Then you can translate the content and import it again (to be done).

## Configuration

You can configure fields you don't want to export, e.g. SiteTree's URLSegment:

```yml
SilverStripe\CMS\Model\SiteTree:
  translate_ignore:
    - URLSegment
```


## Todo
- [X] Export translations to YML
- [ ] Import translations from YML
- [ ] other file adapters like json or xliff
- [ ] better UI, e.g. in LocaleAdmin


## Thanks to
Thanks to [Nobrainer](https://www.nobrainer.dk/) for sponsoring this module ❤️.

Thanks to TractorCow and all contributors for the great [fluent module](https://github.com/tractorcow-farm/silverstripe-fluent). And thanks to the folks at Silverstripe for their great work.

## Need Help?

If you need some help with your Silverstripe project, feel free to [contact me](mailto:werner.krauss@netwerkstatt.at) ✉️.

See you at next [StripeCon](https://stripecon.eu) 👋
