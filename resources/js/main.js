import axios from 'axios';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
});

export function setToken(token) {
  localStorage.setItem('token', token);
  api.defaults.headers.common.Authorization = `Bearer ${token}`;
}

const saved = localStorage.getItem('token');
if (saved) setToken(saved);
