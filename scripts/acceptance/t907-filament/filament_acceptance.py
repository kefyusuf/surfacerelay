"""Mounted real Filament HTTP acceptance; catches missing/reforged selection authority."""
import html
import json
import os
import re
import sqlite3
import unittest
import urllib.request
import urllib.error
import http.cookiejar
import time
import hashlib
import subprocess
from pathlib import Path
from urllib.parse import urlsplit

class Browser:
    def __init__(self):
        self.url = os.environ.get('PILOT_URL','http://127.0.0.1:8000')
        self.http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ''
        self.csrf = self.request('/session')[1]['csrfToken']
        self.snapshot = None
    def request(self,path,payload=None):
        headers={'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':self.csrf}
        if path == '/livewire/update': headers['X-Livewire']='true'
        req=urllib.request.Request(self.url+path,data=None if payload is None else json.dumps(payload).encode(),headers=headers)
        try: response=self.http.open(req,timeout=30)
        except urllib.error.HTTPError as error: response=error
        with response:
            raw=response.read().decode()
            body=json.loads(raw) if 'application/json' in response.headers.get('Content-Type','') else raw
            if isinstance(body,dict) and 'csrfToken' in body: self.csrf=body['csrfToken']
            return response.status,body
    def login(self,user='owner'):
        return self.request('/login',{'email':user+'@example.test','password':'acceptance-password'})
    def mount(self,path='/admin/orders'):
        status,body=self.request(path)
        assert status==200,(status,body)
        snapshots=[html.unescape(s) for s in re.findall(r'wire:snapshot="([^"]+)"',body)]
        self.snapshot=next(s for s in snapshots if json.loads(s)['data'].get('bindingId'))
        return body
    def call(self,method,*params,updates=None,snapshot=None):
        status,body=self.request('/livewire/update',{'_token':self.csrf,'components':[{'snapshot':snapshot or self.snapshot,'updates':updates or {},'calls':[{'path':'','method':method,'params':list(params)}]}]})
        if status==200:
            component=body['components'][0]
            self.snapshot=component['snapshot']
            return status,component['effects'].get('returns',[None])[0],body
        return status,body,body

class FilamentAcceptance(unittest.TestCase):
    database=os.environ.get('PILOT_DATABASE','/tmp/pilot/database/acceptance.sqlite')
    def setUp(self):
        self.browser=Browser()
        self.assertEqual(self.browser.login()[0],200)
        self.browser.mount()
        self.before=self.effects()
    def effects(self):
        with sqlite3.connect(self.database) as connection: return connection.execute('select count(*) from effects').fetchone()[0]
    def pending(self,ids=('101',),method='refundSelected'):
        response=self.browser.call(method,'customer-request',updates={'selectedTableRecords':list(ids)} if method=='refundSelected' else {})
        self.assertEqual(response[0],200)
        self.assertEqual(response[1]['status'],'confirmation_required')
        self.assertEqual(self.effects(),self.before)
        self.assertIsNotNone(json.loads(self.browser.snapshot)['data']['surfaceRelayConfirmationChallengeId'])
    def approve(self):
        result=self.browser.call('callMountedAction')
        self.assertEqual(result[0],200)
        self.assertEqual(self.effects(),self.before)
        return result[2]
    def test_actual_filament_modal_approval_retry_and_replay_execute_once(self):
        self.pending(('101','102'))
        self.approve()
        result=self.browser.call('refundSelected','customer-request')
        self.assertEqual(result[0],200)
        self.assertEqual(result[1]['status'],'succeeded')
        self.assertEqual(result[1]['data'],{'orderIds':[101,102],'affectedCount':2})
        self.assertEqual(self.effects(),self.before+1)
        self.assertEqual(self.browser.call('refundSelected','customer-request')[1]['data'],result[1]['data'])
        self.assertEqual(self.effects(),self.before+1)
    def test_current_record_uses_real_edit_page_context(self):
        self.browser.mount('/admin/orders/101/edit')
        self.pending(method='holdCurrent'); self.approve()
        result=self.browser.call('holdCurrent','customer-request')
        self.assertEqual(result[0],200)
        self.assertEqual(result[1]['data'],{'orderIds':[101],'affectedCount':1})
        self.assertEqual(self.effects(),self.before+1)
    def test_foreign_id_mixed_with_approved_selection_cannot_partially_execute(self):
        self.pending(); self.approve()
        response=self.browser.call('refundSelected','customer-request',updates={'selectedTableRecords':['101','201']})
        self.assertNotEqual(response[1].get('status'),'succeeded')
        self.assertEqual(self.effects(),self.before)
    def test_changed_selection_after_approval_requires_fresh_confirmation(self):
        self.pending(); self.approve()
        self.pending(('102',))
        self.assertEqual(self.effects(),self.before)
    def test_permission_revocation_rejects_retry_and_removes_discovery(self):
        self.pending(); self.approve()
        with sqlite3.connect(self.database) as c: c.execute("update memberships set can_hold=0 where user_id=1 and tenant_id='tenant-a'")
        try:
            result=self.browser.call('refundSelected','customer-request')
            self.assertEqual(result[0],200)
            self.assertEqual(result[1]['error']['code'],'authorization_denied')
            self.assertIn('data-surfacerelay-bindings>[]</script>',result[2]['components'][0]['effects']['html'])
            self.assertEqual(self.effects(),self.before)
        finally:
            with sqlite3.connect(self.database) as c: c.execute("update memberships set can_hold=1 where user_id=1 and tenant_id='tenant-a'")
    def test_tenant_switch_rejects_old_snapshot_and_displays_new_tenant(self):
        self.pending(); self.approve(); old=self.browser.snapshot
        self.assertEqual(self.browser.request('/tenant',{'tenantId':'tenant-b'})[0],200)
        self.assertEqual(self.browser.call('refundSelected','customer-request',snapshot=old)[0],409)
        page=self.browser.mount()
        self.assertIn('value="201"',page); self.assertNotIn('value="101"',page)
        self.pending(('201',))
    def test_remount_rejects_old_signed_snapshot(self):
        self.pending(); self.approve(); old=self.browser.snapshot
        self.browser.mount()
        self.assertEqual(self.browser.call('refundSelected','customer-request',snapshot=old)[0],409)
        self.assertEqual(self.effects(),self.before)
    def test_edit_other_record_rejects_old_approved_snapshot(self):
        self.browser.mount('/admin/orders/101/edit'); self.pending(method='holdCurrent'); self.approve()
        old=self.browser.snapshot
        self.browser.mount('/admin/orders/102/edit')
        self.assertEqual(self.browser.call('holdCurrent','customer-request',snapshot=old)[0],409)
        self.assertEqual(self.effects(),self.before)
        self.pending(method='holdCurrent')
    def test_foreign_edit_record_is_not_mounted(self):
        self.assertEqual(self.browser.request('/admin/orders/201/edit')[0],404)
        self.assertEqual(self.effects(),self.before)
    def test_expired_receipt_requires_new_approval(self):
        self.pending(); self.approve()
        time.sleep(max(5,min(120,int(os.environ.get('PILOT_CONFIRMATION_TTL','5'))))+1)
        self.pending()
    def test_another_session_cannot_use_owner_snapshot(self):
        self.pending(); self.approve()
        other=Browser(); self.assertEqual(other.login()[0],200)
        self.assertEqual(other.call('refundSelected','customer-request',snapshot=self.browser.snapshot)[0],409)
        self.assertEqual(self.effects(),self.before)
        self.assertEqual(self.browser.call('refundSelected','customer-request')[1]['status'],'succeeded')
        self.assertEqual(self.effects(),self.before+1)
    def test_binding_expiry_rejects_approved_retry(self):
        self.pending(); self.approve()
        binding=json.loads(self.browser.snapshot)['data']['bindingId']
        with sqlite3.connect(self.database) as c: c.execute('update pilot_livewire_bindings set expires_at=0 where binding_id=?',(binding,))
        self.assertEqual(self.browser.call('refundSelected','customer-request')[0],409)
        self.assertEqual(self.effects(),self.before)
    def test_caller_receipt_and_locked_binding_cannot_grant_authority(self):
        response=self.browser.call('refundSelected','customer-request','forged-receipt')
        self.assertEqual(response[0],422)
        response=self.browser.call('refundSelected','customer-request',updates={'bindingId':'forged-binding'})
        self.assertGreaterEqual(response[0],400)
        self.assertEqual(self.effects(),self.before)
    def test_empty_and_tracking_all_selection_cannot_use_approval(self):
        self.pending(); self.approve()
        response=self.browser.call('refundSelected','customer-request',updates={'selectedTableRecords':[]})
        self.assertEqual(response[0],200)
        self.assertEqual(response[1]['error']['code'],'required_context_missing')
        response=self.browser.call('refundSelected','customer-request',updates={'isTrackingDeselectedTableRecords':True})
        self.assertEqual(response[0],422)
        self.assertEqual(self.effects(),self.before)
    def test_old_modal_approval_after_remount_is_rejected(self):
        self.pending(); old=self.browser.snapshot; self.browser.mount()
        self.assertEqual(self.browser.call('callMountedAction',snapshot=old)[0],409)
        self.assertEqual(self.effects(),self.before)
    def test_superseded_displayed_challenge_cannot_approve_old_signed_snapshot(self):
        self.pending(); old=self.browser.snapshot
        status,body=self.browser.request('/livewire/update',{'_token':self.browser.csrf,'components':[{'snapshot':self.browser.snapshot,'updates':{},'calls':[
            {'path':'','method':'clearSurfaceRelayConfirmation','params':[]},
            {'path':'','method':'unmountAction','params':[]}]}]})
        self.assertEqual(status,200)
        self.browser.snapshot=body['components'][0]['snapshot']
        result=self.browser.call('refundSelected','duplicate-order')
        self.assertEqual(result[0],200)
        self.assertEqual(result[1]['status'],'confirmation_required')
        current=self.browser.snapshot
        result=self.browser.call('callMountedAction',snapshot=old)
        if result[0] == 200:
            self.browser.call('refundSelected','customer-request')
        self.assertEqual(self.effects(),self.before)
        self.assertEqual(result[0],409)
        self.browser.snapshot=current
        self.approve()
        result=self.browser.call('refundSelected','duplicate-order')
        self.assertEqual(result[0],200)
        self.assertEqual(result[1]['status'],'succeeded')
        self.assertEqual(self.effects(),self.before+1)
    def test_edit102_uses_its_exact_record_and_rejects_record_update(self):
        self.browser.mount('/admin/orders/102/edit')
        response=self.browser.call('holdCurrent','customer-request',updates={'record':201})
        self.assertGreaterEqual(response[0],400)
        self.assertEqual(self.effects(),self.before)
        self.pending(method='holdCurrent'); self.approve()
        result=self.browser.call('holdCurrent','customer-request')
        self.assertEqual(result[0],200)
        self.assertEqual(result[1]['data'],{'orderIds':[102],'affectedCount':1})
        self.assertEqual(self.effects(),self.before+1)
    def test_revoked_permission_approval_grants_no_execution_authority(self):
        self.pending()
        with sqlite3.connect(self.database) as c: c.execute("update memberships set can_hold=0 where user_id=1 and tenant_id='tenant-a'")
        try:
            self.assertEqual(self.browser.call('callMountedAction')[0],403)
            self.assertEqual(self.effects(),self.before)
            response=self.browser.call('refundSelected','customer-request')
            self.assertEqual(response[0],200)
            self.assertEqual(response[1]['error']['code'],'authorization_denied')
            self.assertEqual(self.effects(),self.before)
        finally:
            with sqlite3.connect(self.database) as c: c.execute("update memberships set can_hold=1 where user_id=1 and tenant_id='tenant-a'")
    def test_approved_snapshot_and_audit_disclose_no_receipt_or_raw_output(self):
        self.pending(); approval=self.approve()
        component=json.loads(self.browser.snapshot)['memo']['id']
        session_dir=Path(self.database).parent.parent/'storage/framework/sessions'
        # CLI observer returns only a fingerprint; bearer never crosses this test boundary.
        php="""$key='surfacerelay.filament.confirmation.approved.'.hash('sha256',$argv[2]);
foreach(glob($argv[1].'/*') as $file){$raw=file_get_contents($file);$data=json_decode($raw,true);
if(!is_array($data))$data=@unserialize($raw,['allowed_classes'=>false]);
$value=$data;foreach(explode('.',$key) as $part){$value=is_array($value)?($value[$part]??null):null;}
if(is_string($value)){echo hash('sha256',$value);exit(0);}}exit(1);"""
        observer=subprocess.run(['php','-r',php,str(session_dir),component],capture_output=True,text=True)
        self.assertEqual(observer.returncode,0,'Approved receipt observer must find the exact component receipt')
        receipt_hash=observer.stdout
        self.assertTrue(re.fullmatch('[a-f0-9]{64}',receipt_hash) is not None,'Observer must return only a fingerprint')
        data=json.loads(self.browser.snapshot)['data']
        self.assertIsNone(data['surfaceRelayConfirmationChallengeId'])
        self.assertNotIn('receipt',json.dumps(data).lower())
        self.assertNotIn('FILAMENT_RAW_OUTPUT_SECRET',self.browser.snapshot)
        execution=self.browser.call('refundSelected','customer-request')
        self.assertEqual(execution[1]['status'],'succeeded')
        with sqlite3.connect(self.database) as c:
            audits=c.execute('select * from surfacerelay_audit_events').fetchall()
        self.assertNotIn('receipt',json.dumps(audits).lower())
        self.assertNotIn('FILAMENT_RAW_OUTPUT_SECRET',json.dumps(audits))
        observed=json.dumps([approval,execution[2],audits])
        self.assertTrue(receipt_hash not in observed,'Receipt address must not appear in responses or complete audit rows')
        candidates=re.findall(r'(?<![A-Za-z0-9_-])[A-Za-z0-9_-]{43}(?![A-Za-z0-9_-])',observed)
        self.assertTrue(all(hashlib.sha256(token.encode()).hexdigest()!=receipt_hash for token in candidates),
            'Receipt bearer must not appear in approval/execution responses or complete audit rows')
    def test_rendered_local_panel_assets_are_real_css_and_javascript(self):
        page=self.browser.mount()
        assets=set(re.findall(r'(?:src|href)="([^" ]+)"',page))
        assets={p for p in assets if '/css/filament/' in p or '/js/filament/' in p or '/assets/client.js' in p}
        self.assertGreater(len(assets),3)
        for asset in assets:
            parsed=urlsplit(asset); origin=urlsplit(self.browser.url)
            if parsed.netloc: self.assertEqual((parsed.scheme,parsed.netloc),(origin.scheme,origin.netloc),'Panel asset must share configured application origin')
            path=parsed.path+('?' + parsed.query if parsed.query else '')
            status,body=self.browser.request(path)
            self.assertEqual(status,200,path)
            self.assertIsInstance(body,str,path)
            self.assertNotIn('<!DOCTYPE html>',body[:100],path)

if __name__=='__main__': unittest.main(verbosity=2)
