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

  authDevice: (deviceId) =>
    request('/api/auth/device', { method: 'POST', body: { deviceId } }),

  getMe: () => request('/api/auth/me'),

  updateMe: (patch) =>
    request('/api/auth/me', { method: 'PUT', body: patch }),

  getInterests: () => request('/api/interests'),

  seedInterests: () => request('/api/interests/seed', { method: 'POST' }),
};
