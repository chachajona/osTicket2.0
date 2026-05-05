import { useState, useEffect, useCallback, useRef } from 'react';
import { router } from '@inertiajs/react';

export type LockStatus = 'free' | 'held-by-me' | 'held-by-other';

export interface LockState {
  status: LockStatus;
  heldByStaffId: number | null;
  expiresAt: string | null;
}

interface Options {
  ticketId: number;
  mode: 'disabled' | 'on_view' | 'on_activity';
  initial?: LockState;
}

export function useTicketLock({ ticketId, mode, initial }: Options) {
  const [state, setState] = useState<LockState>(initial || {
    status: 'free',
    heldByStaffId: null,
    expiresAt: null,
  });

  const statusRef = useRef(state.status);
  useEffect(() => {
    statusRef.current = state.status;
  }, [state.status]);

  const getHeaders = () => {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };

    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    if (!csrfToken) {
      const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
      if (match) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(match[2]);
      }
    }

    return headers;
  };

  const acquire = useCallback(async () => {
    try {
      const response = await fetch(`/scp/tickets/${ticketId}/lock`, {
        method: 'POST',
        headers: getHeaders(),
        credentials: 'same-origin',
      });

      if (response.status === 423) {
        const data = await response.json();
        setState({
          status: 'held-by-other',
          heldByStaffId: data.held_by_staff_id,
          expiresAt: data.expires_at,
        });
        return false;
      }

      if (response.ok) {
        const data = await response.json();
        setState({
          status: 'held-by-me',
          heldByStaffId: data.held_by_staff_id,
          expiresAt: data.expires_at,
        });
        return true;
      }
    } catch (error) {
      console.error('Failed to acquire lock:', error);
    }
    return false;
  }, [ticketId]);

  const renew = useCallback(async () => {
    try {
      const response = await fetch(`/scp/tickets/${ticketId}/lock/renew`, {
        method: 'POST',
        headers: getHeaders(),
        credentials: 'same-origin',
      });

      if (response.ok) {
        const data = await response.json();
        setState(prev => ({
          ...prev,
          expiresAt: data.expires_at,
        }));
        return true;
      }
    } catch (error) {
      console.error('Failed to renew lock:', error);
    }
    return false;
  }, [ticketId]);

  const release = useCallback(async () => {
    try {
      const response = await fetch(`/scp/tickets/${ticketId}/lock`, {
        method: 'DELETE',
        headers: getHeaders(),
        credentials: 'same-origin',
        keepalive: true,
      });

      if (response.ok || response.status === 204) {
        setState({
          status: 'free',
          heldByStaffId: null,
          expiresAt: null,
        });
        return true;
      }
    } catch (error) {
      console.error('Failed to release lock:', error);
    }
    return false;
  }, [ticketId]);

  useEffect(() => {
    if (mode === 'on_view') {
      acquire();
    }
  }, [mode, acquire]);

  useEffect(() => {
    let intervalId: ReturnType<typeof setInterval>;

    if (state.status === 'held-by-me') {
      intervalId = setInterval(() => {
        renew();
      }, 30000);
    }

    return () => {
      if (intervalId) clearInterval(intervalId);
    };
  }, [state.status, renew]);

  useEffect(() => {
    const handleRelease = () => {
      if (statusRef.current === 'held-by-me') {
        release();
      }
    };

    const unsubscribeInertia = router.on('navigate', handleRelease);
    window.addEventListener('pagehide', handleRelease);

    return () => {
      window.removeEventListener('pagehide', handleRelease);
      unsubscribeInertia();
      handleRelease();
    };
  }, [release]);

  return { state, acquire, renew, release };
}
