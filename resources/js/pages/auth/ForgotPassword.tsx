import { FormEvent, useState } from 'react';
import { Head, router } from '@inertiajs/react';

export default function ForgotPassword({ status }: { status?: string }) {
  const [email, setEmail] = useState('');
  const [processing, setProcessing] = useState(false);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    router.post(
      '/forgot-password',
      { email },
      {
        onFinish: () => setProcessing(false),
      }
    );
  };

  return (
    <div className="mx-auto max-w-md p-6">
      <Head title="Forgot Password" />
      <h1 className="mb-4 text-2xl font-semibold">Forgot Password</h1>
      <p className="mb-4 text-sm text-gray-600">Enter your email and we will send a reset link.</p>

      {status ? <div className="mb-4 rounded bg-green-100 p-3 text-sm text-green-700">{status}</div> : null}

      <form onSubmit={submit} className="space-y-3">
        <input
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="w-full rounded border px-3 py-2"
          placeholder="Email"
          required
        />
        <button
          type="submit"
          disabled={processing}
          className="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-60"
        >
          {processing ? 'Sending...' : 'Send Reset Link'}
        </button>
      </form>
    </div>
  );
}
