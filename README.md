Labels
======

This extension allows you to use translatable labels for your site. While it
does not allow for fully multilingual sites, you can easily translate labels
and short snippets of text to different languages.

After installation, there will be a new menu-item to access the page where you
can modify and mange the translations:

![screen shot 2015-10-22 at 15 06 15](https://cloud.githubusercontent.com/assets/1833361/10665901/8cb062aa-78ce-11e5-8742-5b1a2e142b6a.png)

If you go to that page, you'll be able to manage the translations in a
spreadsheet-like grid:

![screen shot 2015-10-22 at 15 06 31](https://cloud.githubusercontent.com/assets/1833361/10665902/8cb2345e-78ce-11e5-9203-031146dc0976.png)


Configuration
-------------
The configuration file is pretty self-explanatory. If you're upgrading from a
previous version of this extension, please read the `config.yml.dist` file, to
see what's been changed.

**Options:**

 - `languages`: The array of supported languages. It's advisable to stick to
   two-letter language codes.
 - `default`: The default language to choose, if none is explicitly set using
   `lang` or `setlanguage` (see below).
 - `add_missing`: Whether or not to automatically add missing labels to the
   translation file.
 - `use_fallback`: Fallback to the 'default language', if the label is not
   defined in the selected language? If set to `false` the extension will
   return the untranslated label for display in the browser.


Options for setting the language
--------------------------------
This extensions uses the lang parameter that can be set in a number of ways:

 1. By passing it along in the request: `example.org?lang=nl`
 2. By using the host name: `nl.example.org`
 3. By prefixing a route [as explained in the docs](https://docs.bolt.cm/howto/building-multilingual-websites#defining-routes): 
    `example.org/nl/pages`
 4. By extracting a value from a given locale "nl_NL" and checking whether this
    is a defined value in the `languages` configuration
 5. By using the default from the configuration file
 6. By simply overriding all of these and setting it in the header of your twig
    template files: `{% set lang = 'de' %}`

Usage in templates
------------------

Basic usage: `{{ l('click here') }}`

Note: it's advisable to keep the _labels_ as well as the _language_ names as
lowercase. The actual translated labels are case sensitive, and will be used,
as they are provided in the translation table.

The label that is returned for output in the browser depends on the current
language setting. You can pass this explitly, using: `{{ l('click here', 'nl')
}}`, but it's usually preferable to set this once in the header of your
template.

```
{{ setlanguage('fr') }}

..

{{ l('click here') }} -> returns label in french.

```

When working on a website, it's probably easiest to just create the templates,
adding `{{ l('foo') }}` tags where applicable. If the `add_missing` option in
the config file is set, these will be added to the labels file automatically,
once they've been encountered in the templates. This allows you to work on the
templates, and then translate all of the labels at once.

If you're working on a larger site, it might be advisable to use a namespace-
like structure, to specify the context where a label is used. For example, this
would lead to confusion:

```
<p>
    To go to the frontpage, click <a href='/'>{{ l('home') }}</a>.
</p>

<p>
    {{ l('home') }} is where the heart is.
</p>
```

These would be translated differently in most languages. As such, it's good to
prefix them with a 'namespace' to make the context clear. There are no set pre-
defined namespaces. Pick and choose whatever suits your project. For example:

```
<p>
    To go to the frontpage, click <a href='/'>{{ l('navigation:home') }}</a>.
</p>

<p>
    {{ l('text:home') }} is where the heart is.
</p>
```

Tip: To modify the output of labels, you can use `capitalize`, `lower` and
`upper`. For example:

```
{{ l('hello') }} -> hallo
{{ l('hello')|capitalize }} -> Hallo
{{ l('hello')|lower }} -> Hallo
{{ l('hello')|upper }} -> HALLO
```

Tip: Always keep a backup of the translation file. You never know what might
happen, and if it (for some reason) gets corrupted, it will be good to have a
recent backup available. By default the file is located at 
`app/config/extensions/labels.json`.

