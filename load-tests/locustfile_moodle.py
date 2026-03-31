"""
ESMOS Moodle 4.5 Load Test
Scenario: login → course view → dashboard → course section → logout
Target:   50 concurrent users
"""
import re
import random
from locust import HttpUser, task, between

STAFF_ACCOUNTS = [
    ("admin", "moodle2140"),
    ("staff1", "Esmos2024!"),
    ("staff2", "Esmos2024!"),
    ("staff3", "Esmos2024!"),
]


class MoodleUser(HttpUser):
    wait_time = between(1, 3)
    course_id = "2"  # fallback — overridden in on_start if found

    def on_start(self):
        self._login()
        self._resolve_course_id()

    def _login(self):
        # Step 1: GET login page for logintoken (Moodle CSRF)
        r = self.client.get(
            "/login/index.php",
            verify=False,
            name="GET /login/index.php",
        )
        match = re.search(r'name="logintoken"\s+value="([^"]+)"', r.text)
        login_token = match.group(1) if match else ""

        # Step 2: POST credentials — spread load across staff accounts
        username, password = random.choice(STAFF_ACCOUNTS)
        self.client.post(
            "/login/index.php",
            data={
                "username": username,
                "password": password,
                "logintoken": login_token,
            },
            headers={"Content-Type": "application/x-www-form-urlencoded"},
            verify=False,
            name="POST /login/index.php",
            allow_redirects=True,
        )

    def _resolve_course_id(self):
        r = self.client.get(
            "/course/index.php",
            verify=False,
            name="GET /course/index.php",
        )
        match = re.search(
            r'href="[^"]*course/view\.php\?id=(\d+)"[^>]*>[^<]*ESMOS',
            r.text,
        )
        if match:
            self.course_id = match.group(1)

    @task(3)
    def view_course(self):
        self.client.get(
            f"/course/view.php?id={self.course_id}",
            verify=False,
            name="GET /course/view.php",
        )

    @task(2)
    def view_dashboard(self):
        self.client.get(
            "/my/",
            verify=False,
            name="GET /my/ (dashboard)",
        )

    @task(1)
    def view_course_section(self):
        self.client.get(
            f"/course/view.php?id={self.course_id}&section=1",
            verify=False,
            name="GET /course/view.php?section=1",
        )

    def on_stop(self):
        self.client.get(
            "/login/logout.php",
            verify=False,
            name="GET /login/logout.php",
        )
