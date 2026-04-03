import { Head, router } from '@inertiajs/react';

export default function VerifyEmail({ status }: { status?: string }) {
  return (
    <div className="mx-auto max-w-md p-6">
      <Head title="Verify Email" />
      <h1 className="mb-4 text-2xl font-semibold">Verify Email</h1>
      <p className="mb-4 text-sm text-gray-600">
        Please verify your email address by clicking the link we sent.
      </p>

      {status ? <div className="mb-4 rounded bg-green-100 p-3 text-sm text-green-700">{status}</div> : null}

      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => router.post('/email/verification-notification')}
          className="rounded bg-blue-600 px-4 py-2 text-white"
        >
          Resend verification email
        </button>
        <button
          type="button"
          onClick={() => router.post('/logout')}
          className="rounded border px-4 py-2"
        >
          Logout
        </button>
      </div>
    </div>
  );
}
