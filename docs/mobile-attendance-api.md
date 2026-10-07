# Mobile attendance API

All employee endpoints require the existing JWT bearer token. Admin correction
review and attendance-photo endpoints require an admin JWT. The API continues
to use `/api/login`, `/api/logout`, `/api/me`, `/api/attendance/time-in`, and
`/api/attendance/today`.

## Biometric-gated key integration

The mobile app must generate a private key that remains on the device and is
only usable after the operating system completes the configured biometric
check. Never upload fingerprint data, face templates, or the private key.

1. After login, the employee explicitly chooses to enable fingerprint
   authentication. The Android app must show the system biometric prompt
   before generating a biometric-gated device key; cancel or failure must not
   generate or register a key. With the authenticated employee JWT, post
   `verification_method: "fingerprint"`, `device_name`, the employee
   `password`, and `public_key_pem` to `/api/biometric/credentials`.
   Face enrollment is currently rejected until a face identity and liveness
   verification provider is configured. The API associates this pending key
   with the employee resolved from the authenticated JWT and rejects a key
   already registered to any ATTENDSYNC account. Generate a new device key
   after account changes; never silently reuse another account's key.
2. To prove enrollment, post the returned `credential_id`,
   `verification_method`, and `purpose: "biometric-enrollment"` to
   `/api/biometric/challenges`.
3. Request a fresh Android system biometric prompt. Only after successful
   local authentication should the Android Keystore authorize the private key
   to sign the exact returned `message_to_sign`. Post `challenge_id` and the
   base64-encoded signature to `/api/biometric/verify`. The server stores the
   public key as pending until this signature is verified. Enrollment becomes
   active only when the response contains `credential_verified: true`; then
   read `GET /api/biometric/credentials` to confirm its server status.
4. For Time In, request a new challenge without an enrollment purpose, sign
   it, and post the signature to `/api/biometric/verify`. Submit the returned
   `biometric_verification_token` with the multipart Time In request. The
   token expires after five minutes and is single-use.

The signed message is the exact UTF-8 string returned as `message_to_sign`.
The server verifies its SHA-256 signature against the public key enrolled to
the authenticated employee; a client-supplied `biometric_verified` field is
not used. Enrollment currently binds a key through password reauthentication,
but this backend does not validate Android/iOS hardware key attestation. The
mobile app must enforce biometric-only key use, and a production deployment
requiring proof of that policy needs platform attestation verification.
Independent face matching/liveness requires a trusted identity-verification
provider or a vetted on-device attestation architecture.

After login, check enrollment with `GET /api/biometric/credentials`. The
`methods.fingerprint.enrolled` and `methods.face.enrolled` values reflect keys
actually registered with the backend; they must not be inferred from local
permissions or a screen visit. Registration is confirmed only by a successful
`POST /api/biometric/credentials` response. To disable a method or remove one
device, send `DELETE /api/biometric/credentials` with the employee password,
`verification_method`, and optionally the matching `credential_id`. Password
login remains available after credentials are removed.

The mobile biometric library returns the public key as base64-encoded DER
SubjectPublicKeyInfo; wrap it in the standard `PUBLIC KEY` PEM header/footer
and 64-character lines before sending `public_key_pem`. Only the
`fingerprint` verification method is currently accepted by the backend.
Android's generic `BiometricPrompt` may authenticate with another supported
enrolled modality, so the method label must not be read as server proof that a
fingerprint specifically was used. The `face` key method does not upload or
register a face template. Raw face templates should not be accepted by this
API without a separate encrypted biometric-template service, key management,
identity/liveness verification, and retention/deletion policy. Camera
permission or local face detection is not backend-confirmed enrollment and
must not cause the UI to show it as enrolled.

## Time In

Post `/api/attendance/time-in` as `multipart/form-data` with:

- `photo`: JPEG or PNG, maximum 5 MB.
- `biometric_verification_token`: one-use token from the verified challenge.

Do not send an employee ID. The employee is resolved from the authenticated
JWT. Attendance photos are stored on Laravel's private `local` disk and are
not exposed as public files. The attendance response includes `photo_url`;
fetch it with the same employee bearer token (the employee can access only
their own photo; admins can access all photos). The timestamp in the
attendance response records when Time In happened and is not the photo URL or
the photo itself. The admin web attendance list also provides a photo link.

The saved attendance payload contains the actual record. `present` means On
Time and `late` means Late; the `late_starting_time_snapshot` field captures
the schedule threshold used for classification. Schedules use the latest
effective date not later than the attendance date. The application timezone
defaults to `Asia/Manila` and can be overridden with `APP_TIMEZONE`.
Both successful Time In and today's attendance return `attendance_date`,
`time_in`, `status`, and `timezone`; `time_in` is an ISO-8601 timestamp with
the `+08:00` offset. Display this server-returned saved record, refresh
`GET /api/attendance/today` after a successful submission, and show an error
instead of inventing an official timestamp when either request fails. Keep
photo capture metadata separate from this official timestamp. Correction
requests likewise display the request and review status returned by the API.

## Attendance corrections

Employees can create/list/show their own requests at
`/api/attendance-correction-requests`. Requests include `attendance_date`,
`request_type`, optional `requested_time_in` / `requested_time_out` in `HH:mm`,
and `reason`. An employee may not submit a second pending request for the
same date.

Admins can list/show requests under
`/api/admin/attendance-correction-requests`, then post to
`/{id}/approve` or `/{id}/reject`, optionally including
`admin_review_notes`. Approval applies the requested correction inside a
database transaction and records original values, approved values, reviewer,
review time, and the employee's reason. Corrections do not create biometric
verification grants or authorize a new mobile Time In.

Administrators can also review requests in the web dashboard at
`/admin/attendance-correction-requests`. The inbox supports status filtering;
the request detail page shows the employee's reason, requested and current
attendance values, then provides approve/apply and reject actions. Both API
and web review use the same transactional correction service and retain the
same audit data.
