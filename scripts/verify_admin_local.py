"""Local browser QA. Read credentials privately; never print or archive submitted secrets."""
import json
from browser_qa_local import run_scenario, write_report

result = run_scenario('admin')
assert result['login'] and result['logout'] and result['csrfRefused'] and result['emptySearch'] and result['noJavascript']
assert not result.get('failure') and not result['errors']
assert all(row['status']==200 and row['h1']==1 and not row['overflow'] and row['private'] and row['noindex'] for row in result['pages'])
write_report('admin-browser.json', result)
print(json.dumps(result))
