/**
 * Types del dominio Customer (B2B/B2C).
 *
 * Refleja la shape de `CustomerResource` (en backend) y la respuesta
 * del endpoint `POST /api/v1/storefront/auth/register` (CA.2).
 *
 * Customer se vincula a un User via `customer.user_id`. Un User con
 * role='customer' puede tener 0 o 1 Customer profile (1:1). El
 * frontend usa este type para hidratar al customer después del
 * register/login y para mostrar el dashboard del account
 * (F2.2 task-oriented spec §4.1).
 */

export type CustomerType = 'retail' | 'wholesale' | 'vip';

export interface Customer {
  id: number;
  /** FK a `users.id` (nullable en BD, pero CA.2 lo setea siempre). */
  user_id: number | null;
  customer_type: CustomerType;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  registered_at: string;
}
