"""Authorized local browser QA. Only named synthetic messages and an unpublished draft.

Credentials and customer access codes are never printed, captured or archived.
No real order, payment, email, integration credentials or existing product is changed.
"""
import json
from browser_qa_local import run_scenario, write_report

result = run_scenario('management')
write_report('admin-management-live.json', result)
print(json.dumps(result))
assert not result.get('failure') and not result['errors'] and not result['externalRequests']
for key in ['customerCreated','literalMessage','noInjectedScript','privateConversation','login','replyStored','noEmail','customerReceived','resumed','customerFollowup','draftCreated','draftEdited','draftUnpublished','draftHidden','notifications','csrfRefused','chronological','readState','logout']:
    assert result[key], key
assert len(result['pages'])==33 and all(p['status']==200 and p['h1']==1 and not p['overflow'] and p['private'] and p['noindex'] for p in result['pages'])
