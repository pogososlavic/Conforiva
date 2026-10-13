"""Upload a clearly synthetic QA image to an existing unpublished QA draft only."""
import json
from browser_qa_local import run_scenario, write_report

result = run_scenario('upload')
write_report('admin-management-upload.json', result)
print(json.dumps(result))
assert not result.get('failure') and result['image']['loaded'] and result['image']['webp'] and result['unpublished'] and result['gallery'] and result['settingsCsrfRefused']
