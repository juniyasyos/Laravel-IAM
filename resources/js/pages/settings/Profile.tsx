import React from 'react';

interface ProfileData {
  // Define your profile data structure here
}

export default function Profile({ data }: { data?: ProfileData }) {
  return (
    <div className="p-6">
      <h1 className="text-3xl font-bold mb-6">Profile Settings</h1>
      <div className="bg-white rounded-lg shadow p-6">
        <p className="text-gray-600">Profile settings content goes here</p>
      </div>
    </div>
  );
}
