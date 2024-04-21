Layout Paragraphs Restrictions

Provides a simple means of restricting pargraph types in Mercury Editor
Layout Paragraph instances by matching context variables.

Syntax is as follows:

{context}={value}: # i.e. region=_root or parent_type=section
  components:
    - type_1
    - type_2
  exclude_components:
    - type_3
    - type_4


