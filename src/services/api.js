import { API_BASE_URL } from '../config';

let authToken = null;

export const setAuthToken = (token) => {
  authToken = token;
};

export const getAuthToken = () => authToken;

async function request(path, { method = 'GET', body, headers = {} } = {}) {
  const url = `${API_BASE_URL}${path}`;
  const finalHeaders = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...headers,
  };
  if (authToken) {
    finalHeaders.Authorization = `Bearer ${authToken}`;
  }

  const res = await fetch(url, {
    method,
    headers: finalHeaders,
    body: body ? JSON.stringify(body) : undefined,
  });

  const text = await res.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = { raw: text };
  }

  if (!res.ok) {
    const err = new Error(data?.error || `http_${res.status}`);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

export const api = {
  health: () => request('/api/health'),

  register: ({ email, password, username, nickname }) =>
    request('/api/auth/register', { method: 'POST', body: { email, password, username, nickname } }),

  login: (email, password) =>
    request('/api/auth/login', { method: 'POST', body: { email, password } }),

  getMe: () => request('/api/auth/me'),

  updateMe: (patch) =>
    request('/api/auth/me', { method: 'PUT', body: patch }),

  getInterests: () => request('/api/interests'),

  seedInterests: () => request('/api/interests/seed', { method: 'POST' }),

  getUser: (id) => request(`/api/users/${id}`),

  follow: (id) => request(`/api/users/${id}/follow`, { method: 'POST' }),
  unfollow: (id) => request(`/api/users/${id}/follow`, { method: 'DELETE' }),
  getFollowers: (id) => request(`/api/users/${id}/followers`),
  getFollowing: (id) => request(`/api/users/${id}/following`),

  shuffleNext: (params) => {
    const qs = new URLSearchParams(params || {}).toString();
    return request(`/api/shuffle/next${qs ? `?${qs}` : ''}`);
  },
  shuffleStatus: () => request('/api/shuffle/status'),
  upgradePlus: () => request('/api/shuffle/upgrade', { method: 'POST' }),
  downgradePlus: () => request('/api/shuffle/downgrade', { method: 'POST' }),

  getFriends: () => request('/api/friends'),
  getFriendSuggestions: () => request('/api/friends/suggestions'),
  addFriend: (id) => request(`/api/friends/${id}/auto-add`, { method: 'POST' }),
  removeFriend: (id) => request(`/api/friends/${id}`, { method: 'DELETE' }),

  uploadPhoto: ({ recipientId, contentBase64, isOneTime }) =>
    request('/api/photos/upload', {
      method: 'POST',
      body: { recipientId, contentBase64, isOneTime },
    }),
  getPhoto: (id) => request(`/api/photos/${id}`),
  listPhotos: () => request('/api/photos/inbox/list'),
};
