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
  acceptFollow: (id) => request(`/api/users/${id}/follow/accept`, { method: 'POST' }),
  rejectFollow: (id) => request(`/api/users/${id}/follow/reject`, { method: 'POST' }),
  getFollowRequests: () => request('/api/users/me/requests/incoming'),
  getMyFollowers: () => request('/api/users/me/followers'),
  getMyFollowing: () => request('/api/users/me/following'),
  getMyPendingOutgoing: () => request('/api/users/me/pending-outgoing'),
  getFollowers: (id) => request(`/api/users/${id}/followers`),
  getFollowing: (id) => request(`/api/users/${id}/following`),

  shuffleNext: (params) => {
    const qs = new URLSearchParams(params || {}).toString();
    return request(`/api/shuffle/next${qs ? `?${qs}` : ''}`);
  },
  shuffleStatus: () => request('/api/shuffle/status'),
  plusInfo: () => request('/api/shuffle/plus/info'),
  upgradePlus: (tier = '1m') => request('/api/shuffle/upgrade', { method: 'POST', body: { tier } }),
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

  adminLogin: (email, password) =>
    request('/api/admin/login', { method: 'POST', body: { email, password } }),
  adminStats: () => request('/api/admin/stats'),
  adminUsers: (params) => {
    const qs = new URLSearchParams(params || {}).toString();
    return request(`/api/admin/users${qs ? `?${qs}` : ''}`);
  },
  adminGetUser: (id) => request(`/api/admin/users/${id}`),
  adminPatchUser: (id, patch) =>
    request(`/api/admin/users/${id}`, { method: 'PATCH', body: patch }),
  adminDeleteUser: (id) => request(`/api/admin/users/${id}`, { method: 'DELETE' }),

  getChats: () => request('/api/chats'),
  pinChat: (peerId) => request(`/api/chats/${peerId}/pin`, { method: 'POST' }),
  unpinChat: (peerId) => request(`/api/chats/${peerId}/unpin`, { method: 'POST' }),
  deleteChat: (peerId) => request(`/api/chats/${peerId}`, { method: 'DELETE' }),
  markChatRead: (peerId) => request(`/api/chats/${peerId}/read`, { method: 'POST' }),
  syncChat: (peerId, roomId) => request(`/api/chats/sync/${peerId}/${roomId}`, { method: 'POST' }),
};
