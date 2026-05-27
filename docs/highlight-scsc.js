hljs.registerLanguage('scsc', function(hljs) {
  var BUILTIN_TYPES = 'int int8 int16 int32 int64 uint uint8 uint16 uint32 uint64 float float32 float64 double string bool uuid bytes timestamp datetime date time any mixed';

  return {
    name: 'SchemaScript',
    aliases: ['scsc', 'schemascript'],
    keywords: {
      keyword: 'import ns const pub private',
      type: BUILTIN_TYPES,
      literal: 'true false'
    },
    contains: [
      hljs.C_LINE_COMMENT_MODE,
      hljs.APOS_STRING_MODE,
      hljs.QUOTE_STRING_MODE,
      hljs.C_NUMBER_MODE,
      {
        className: 'meta',
        begin: '@[\\w.]+',
        relevance: 5
      },
      {
        className: 'meta',
        begin: '\\[[\\w:.]+\\]',
        relevance: 5
      },
      {
        className: 'title.class',
        begin: '\\b[A-Z]\\w*',
        relevance: 0
      },
      {
        className: 'attr',
        begin: '\\b\\w+(?=\\??\\s*:)',
        relevance: 0
      },
      {
        className: 'punctuation',
        begin: '[{}<>\\[\\]|,?:]',
        relevance: 0
      }
    ]
  };
});

// re-highlight all code blocks after registration
if (typeof document !== 'undefined') {
  document.querySelectorAll('pre code.language-scsc, pre code.hljs.language-scsc').forEach(function(block) {
    hljs.highlightElement(block);
  });
}
