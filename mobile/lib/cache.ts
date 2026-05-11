import AsyncStorage from '@react-native-async-storage/async-storage';

type CacheEnvelope<T> = {
  savedAt: number;
  data: T;
};

export type CachedValue<T> = CacheEnvelope<T>;

export async function readCache<T>(key: string, maxAgeMs?: number): Promise<T | null> {
  const envelope = await readCacheEnvelope<T>(key, maxAgeMs);

  return envelope?.data ?? null;
}

export async function readCacheEnvelope<T>(key: string, maxAgeMs?: number): Promise<CachedValue<T> | null> {
  try {
    const raw = await AsyncStorage.getItem(key);

    if (!raw) {
      return null;
    }

    const envelope = JSON.parse(raw) as CacheEnvelope<T>;

    if (maxAgeMs && Date.now() - envelope.savedAt > maxAgeMs) {
      return null;
    }

    return envelope;
  } catch {
    return null;
  }
}

export async function writeCache<T>(key: string, data: T) {
  try {
    const envelope: CacheEnvelope<T> = {
      savedAt: Date.now(),
      data,
    };

    await AsyncStorage.setItem(key, JSON.stringify(envelope));
  } catch {
    // Ignore cache write failures so mobile flows keep working.
  }
}

export async function removeCache(key: string) {
  try {
    await AsyncStorage.removeItem(key);
  } catch {
    // Ignore cache delete failures.
  }
}

export async function removeCaches(keys: string[]) {
  await Promise.all(keys.map((key) => removeCache(key)));
}
