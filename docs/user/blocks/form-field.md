# Form Field block

One input on a form: a text box, a dropdown, a checkbox, and so on. Add these inside an [RSVP Form](./rsvp-and-inner-blocks.md) to ask attendees for anything beyond their name and email, such as dietary needs, a t-shirt size, or an accessibility request.

Each field you add becomes part of that event's form, and every answer is stored with the RSVP.

## Field types

Pick the type first, because it changes which settings appear:

| Type | For |
|---|---|
| Text | A short free-text answer |
| Email | An email address, checked for a valid format |
| Telephone | A phone number |
| URL | A web address, checked for a valid format |
| Number | A numeric answer, with optional minimum and maximum |
| Textarea | A longer answer, with a row count you choose |
| Checkbox | A single yes or no |
| Radio | One choice from a list, all options visible |
| Select | One choice from a list, in a dropdown |
| Hidden | A value the visitor does not see or fill in |

Email, URL and Number are checked when the form is submitted. An answer that does not fit is refused and the person is told which field to fix.

## Settings

**Field Name** is how the answer is identified and stored. It is not shown to the visitor. Use something short and descriptive, like `dietary` or `tshirt`. Only letters, numbers, underscores and hyphens are allowed, and the editor strips anything else as you type.

Give each field on a form a different name. Two fields sharing one name will collide.

**Label** is the visible question, such as "Dietary needs".

**Required** marks the field as one the visitor has to answer. This is enforced when the form is submitted, not only in the browser, so an answer cannot be skipped.

**Required Text** is the marker shown beside the label, "(required)" by default.

**Help Text** is a short description below the field. It is announced by screen readers along with the field, so it is a good place for a constraint or an example rather than an instruction that repeats the label.

**Placeholder** is the grayed-out hint inside an empty field. It disappears as soon as someone types, so do not put anything essential there. Use Help Text for that.

**Input ID** is optional, and most forms never need it. Leave it empty and an id is generated. Set one when you need the id to stay the same every time the page loads, for example to point your own label or script at the field. It must be unique on the page.

**Prefill from current user** fills the field automatically for a signed-in visitor: their display name for a text field, their account email for an email field. Handy for a "Name" or "Email" field so members do not retype what you already know.

**Inline layout** puts the label beside the input rather than above it. **Field width** sets how much of the row the field takes, so two fields at 50% sit side by side.

Numbers have **Minimum** and **Maximum**, textareas have **Rows**, and Radio and Select have an options list where each entry has a label people see and a value that gets stored.

## Colors and typography

The sidebar also has controls for label, field, option and required-marker colors, background and border color, font sizes, line heights, padding, border width and corner radius. These follow your theme unless you change them, so start there and only override what you need.

## When an answer is refused

If somebody submits the form with a required field empty, or with an answer that does not fit its type, the RSVP is not recorded. They stay on the form and each field at fault is marked with a message explaining what to fix. Correcting a field clears its message.

Nothing is saved until everything is acceptable, so you will not end up with an RSVP that is missing an answer you asked for.

## See also

- [RSVP block and inner blocks](./rsvp-and-inner-blocks.md)
- [The RSVP system](../rsvp-system.md)
- [Creating and managing events](../creating-and-managing-events.md)
