"""Local UI recipe: synthetic drafts only; no real numbering, sale, email or SQL mutation."""
import json
import sys
from browser_qa_local import run_scenario, write_report

screenshots_only = '--screenshots-only' in sys.argv
if sys.argv[1:] not in ([], ['--screenshots-only']):
    raise SystemExit('Usage: verify_commercial_documents_local.py [--screenshots-only]')
result = run_scenario('documents', screenshots_only=screenshots_only)
if screenshots_only:
    print(json.dumps(result))
    assert result.get('screenshots') == 2 and not result.get('failure')
    raise SystemExit(0)
write_report('documents-browser.json', result)
print(json.dumps(result))
assert not result.get('failure') and not result['errors'] and not result['external']
assert all(result[key] for key in ['login','noInjectedScript','csrfRejected','privateAccess','withoutJs'])
assert len(result['pages']) == 12 and all(p['status']==200 and p['h1']==1 and not p['overflow'] and p['private'] and p['noindex'] for p in result['pages'])
assert len(result['drafts']) == 3 and all(all(d[key] for key in ['redirectCorrect','total','blocked','pdf','privatePdf']) for d in result['drafts'])
