# Templates

This extensions allows your client to create pages according to predefined templates. And when I say pages, I mean
actual Symphony pages!

## Setup

 1. Create a section, and add a 'templates'-field to it.
 2. Create a datasource which uses the section as source, and filter the template field on `{$current-page-id}`. This datasource is used for the page content.
 3. Create one or more pages which you want to use as template. Add (at least) the newly created datasources to it, and give the page the type `template`.

Now, when you create a new entry in the section, the 'templates'-field allows you to set a page title, parent and template.
When the entry is created, the chosen template is copied, including all it's references to datasources, events and parameters.

In the publish page, the user has the possibility to change to order of the pages.

## Notes

 - If you want a page to hide from the parent-picker when creating an entry, you can add the type `template_hide` to it. This can be used for hidden or system pages for example.
 - The template is __copied__. So if you make changes to the core template, these do not reflect on the already created pages. Some manually copy-pasting (or re-saving) is required here.
 - The template is resaved __each time you save the entry__. So if you make changes to the pages XSL, re-saving the entry will overwrite the XSL with the templates XSL.

