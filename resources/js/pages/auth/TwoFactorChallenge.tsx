import { FormEvent, useState } from 'react';
import { Head, router } from '@inertiajs/react';

export default function TwoFactorChallenge() {
  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [processing, setProcessing] = useState(false);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    router.post(
      '/two-factor-challenge',
      {
        code: code || undefined,
        recovery_code: recoveryCode || undefined,
      },
      {
        onFinish: () => setProcessing(false),
      }
    );
  };

  return (
    <div className="mx-auto max-w-md p-6">
      <Head title="Two Factor Challenge" />
      <h1 className="mb-4 text-2xl font-semibold">Two-Factor Challenge</h1>
      <form onSubmit={submit} className="space-y-3">
        <input
          type="text"
          value={code}
          onChange={(e) => {
            setCode(e.target.value);
            if (e.target.value) setRecoveryCode('');
          }}
          className="w-full rounded border px-3 py-2"
          placeholder="Authentication code"
        />
        <input
          type="text"
          value={recoveryCode}
          onChange={(e) => {
            setRecoveryCode(e.target.value);
            if (e.target.value) setCode('');
          }}
          className="w-full rounded border px-3 py-2"
          placeholder="Recovery code"
        />
        <button
          type="submit"
          disabled={processing}
          className="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-60"
        >
          {processing ? 'Verifying...' : 'Verify'}
        </button>
      </form>
    </div>
  );
}
