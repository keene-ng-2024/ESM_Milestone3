"""
ESMOS Odoo 17 Load Test
Scenario: login → helpdesk list → ticket search (RPC) → home → logout
Target:   100 concurrent users
"""
import json
import re
from locust import HttpUser, task, between


class OdooUser(HttpUser):
    wait_time = between(1, 3)

    def on_start(self):
        self._login()

    def _login(self):
        # Step 1: GET login page to extract CSRF token
        r = self.client.get(
            "/web/login",
            verify=False,
            name="GET /web/login",
        )
        match = re.search(r'name="csrf_token"\s+value="([^"]+)"', r.text)
        csrf = match.group(1) if match else ""

        # Step 2: POST credentials
        self.client.post(
            "/web/login",
            data={
                "login": "sysconfig@esmos.meals.sg",
                "password": "esm214",
                "csrf_token": csrf,
            },
            headers={"Content-Type": "application/x-www-form-urlencoded"},
            verify=False,
            name="POST /web/login",
            allow_redirects=True,
        )

    @task(3)
    def helpdesk_rpc(self):
        """Fetch open helpdesk tickets — main backend workload."""
        payload = {
            "jsonrpc": "2.0",
            "method": "call",
            "params": {
                "model": "helpdesk.ticket",
                "method": "search_read",
                "args": [[["stage_id.closed", "=", False]]],
                "kwargs": {
                    "fields": ["name", "partner_name", "stage_id", "priority"],
                    "limit": 20,
                },
            },
        }
        self.client.post(
            "/web/dataset/call_kw",
            json=payload,
            verify=False,
            name="RPC helpdesk.ticket/search_read",
        )

    @task(2)
    def odoo_web(self):
        """Load the Odoo SPA shell."""
        self.client.get(
            "/web",
            verify=False,
            name="GET /web",
        )

    @task(1)
    def helpdesk_team_rpc(self):
        """Fetch helpdesk teams — secondary backend workload."""
        payload = {
            "jsonrpc": "2.0",
            "method": "call",
            "params": {
                "model": "helpdesk.ticket.team",
                "method": "search_read",
                "args": [[]],
                "kwargs": {
                    "fields": ["name", "member_ids"],
                    "limit": 10,
                },
            },
        }
        self.client.post(
            "/web/dataset/call_kw",
            json=payload,
            verify=False,
            name="RPC helpdesk.ticket.team/search_read",
        )

    def on_stop(self):
        self.client.post(
            "/web/session/logout",
            verify=False,
            name="POST /web/session/logout",
        )
