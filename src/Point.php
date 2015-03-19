<?php
/******************************************************************************
 * This file is part of the Phactor PHP project. You can always find the latest
 * version of this class and project at: https://github.com/ionux/phactor
 *
 * Copyright (c) 2015 Rich Morgan, rich.l.morgan@gmail.com
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
 * Street, Fifth Floor, Boston, MA 02110-1301 USA.
 ******************************************************************************/

namespace Phactor;

/**
 * This trait implements the elliptic curve math functions required to generate
 * a public/private EC keypair based on the secp256k1 curve parameters.
 *
 * @author Rich Morgan <rich.l.morgan@gmail.com>
 */
trait Point
{
    use Math;

    /**
     * EC Point addition method P + Q = R where:
     *   s = (yP - yQ) / (xP - xQ) mod p
     *   xR = s2 - xP - xQ mod p
     *   yR = -yP + s(xP - xR) mod p
     *
     * @param  array|string $P The first point to add.
     * @param  array|string $Q The second point to add.
     * @return array        $R The result of the point addition.
     * @throws \Exception
     */
    public function pointAdd($P, $Q)
    {
        if (false === isset($P) || true === empty($P)) {
            throw new \Exception('You must provide a valid first point parameter to add.');
        }

        if (false === isset($Q) || true === empty($Q)) {
            throw new \Exception('You must provide a valid second point parameter to add.');
        }

        if ($P == $Q) {
            return $this->pointDouble($P);
        }

        if ($P == $this->Inf || false === is_array($P)) {
            return $Q;
        }

        if ($Q == $this->Inf || false === is_array($Q)) {
            return $P;
        }

        $ss = '0';

        $R = array('x' => '0', 'y' => '0');

        try {
            $mm = $this->Subtract($P['y'], $Q['y']);
            $nn = $this->Subtract($P['x'], $Q['x']);
            $oo = $this->Invert($nn, $this->p);
            $st = $this->Multiply($mm, $oo);
            $ss = $this->Modulo($st, $this->p);

            $R['x'] = $this->Modulo($this->Subtract($this->Subtract($this->Multiply($ss, $ss), $P['x']), $Q['x']), $this->p);
            $R['y'] = $this->Modulo($this->Add($this->Subtract('0', $P['y']), $this->Multiply($ss, $this->Subtract($P['x'], $R['x']))), $this->p);
        } catch (\Exception $e) {
            throw $e;
        }

        return $R;
    }

    /**
     * Point multiplication method 2P = R where
     *   s = (3xP2 + a) / (2yP) mod p
     *   xR = s2 - 2xP mod p
     *   yR = -yP + s(xP - xR) mod p
     *
     * @param  array|string  $P The point to multiply.
     * @return array|string  $R The multiplied point.
     * @throws \Exception
     */
    public function pointDouble($P)
    {
        if (false === isset($P) || true === empty($P)) {
            throw new \Exception('You must provide a valid point parameter to double.');
        }

        if ($P == $this->Inf || false === is_array($P)) {
            return $this->Inf;
        }

        $ss = '0';

        $R = array('x' => '0', 'y' => '0');

        try {
            $mm   = $this->Add($this->Multiply('3', $this->Multiply($P['x'], $P['x'])), $this->a);
            $oo   = $this->Multiply('2', $P['y']);
            $nn   = $this->Invert($oo, $this->p);
            $n2   = $this->Modulo($oo, $this->p);
            $st   = $this->Multiply($mm, $nn);
            $st2  = $this->Multiply($mm, $n2);
            $ss   = $this->Modulo($st, $this->p);
            $s2   = $this->Modulo($st2, $this->p);
            $xmul = $this->Multiply('2', $P['x']);
            $smul = $this->Multiply($ss, $ss);
            $xsub = $this->Subtract($smul, $xmul);
            $xmod = $this->Modulo($xsub, $this->p);

            $R['x'] = $xmod;

            $ysub  = $this->Subtract($P['x'], $R['x']);
            $ymul  = $this->Multiply($ss, $ysub);
            $ysub2 = $this->Subtract('0', $P['y']);
            $yadd  = $this->Add($ysub2, $ymul);

            $R['x'] = $R['x'];
            $R['y'] = $this->Modulo($yadd, $this->p);
        } catch (\Exception $e) {
            throw $e;
        }

        return $R;
    }

    /**
     * Performs a test of an EC point by substituting the new
     * values into the equation for the standard form of the curve.
     *
     * @param  array $P  The generated point to test.
     * @return bool      Whether or not the point is valid.
     * @throws \Exception
     */
    public function PointTest($P)
    {
        if (false === isset($P) || true === empty($P) || $this->Inf == $P || false === is_array($P)) {
            throw new \Exception('You must provide a valid point to test.');
        }

        /*
         * Algebraic form of the elliptic curve:
         * y^2 (mod p) = x^3 + ax + b (mod p)
         */
        $y2    = '';
        $x3    = '';
        $ax    = '';
        $left  = '';
        $right = '';

        try {
            /* Left $y^2 term */
            $y2 = $this->Multiply($P['y'], $P['y']);

            /* Right, first $x^3 term */
            $x3 = $this->Multiply($this->Multiply($P['x'], $P['x']), $P['x']);

            /* Right, second ax term */
            $ax = $this->Multiply($this->a, $P['x']);

            /*
             * If the right side of the equation equals the left,
             * we have a valid point, agebraically speaking.
             */
            $left  = $this->Modulo($y2, $this->p);
            $right = $this->Modulo($this->Add($this->Add($x3, $ax), $this->b), $this->p);
        } catch (\Exception $e) {
            throw $e;
        }

        return $left == $right;
    }

    /**
     * Pure PHP implementation of the doubleAndAdd algorithm, for more info see:
     * http://en.wikipedia.org/wiki/Elliptic_curve_point_multiplication#Double-and-add
     *
     * @param  string       $x Scalar value.
     * @param  array        $P Base EC curve point.
     * @return array|string $S Either 'infinity' or the new coordinates.
     * @throws \Exception
     */
    public function doubleAndAdd($x, $P)
    {
        if (false === isset($P) || true === empty($P) || false == is_array($P)) {
            throw new \Exception('You must provide a valid point to scale.');
        }

        if (false === isset($x) || true === empty($x)) {
            throw new \Exception('Missing or invalid scalar value in doubleAndAdd() function.');
        }

        $tmp = $this->D2B($x);

        $n   = strlen($tmp) - 1;
        $S   = $this->Inf;

        while ($n >= 0) {
            $S = $this->pointDouble($S);

            if ($tmp[$n] == '1') {
                $S = $this->pointAdd($S, $P);
            }

            $n--;
        }

        return $S;
    }

    /**
     * Creates a new point on the elliptic curve.
     *
     * @param string $random_number
     * @throws \Exception
     * @return array
     */
    public function GenerateNewPoint()
    {
        $P = array('x' => strtolower(trim($this->Gx)), 'y' => strtolower(trim($this->Gy)));

        do {
            $random_number = $this->SecureRandomNumber();
        } while ($this->Compare($random_number, '0x01') <= 0 || $this->Compare($random_number, $this->n) >= 0);

        $R = $this->doubleAndAdd($random_number, $P);

        if ($this->PointTest($R)) {
            $Rx_hex = str_pad($this->encodeHex($R['x']), 64, "0", STR_PAD_LEFT);
            $Ry_hex = str_pad($this->encodeHex($R['y']), 64, "0", STR_PAD_LEFT);
        } else {
            throw new \Exception('Point test failed! Cannot continue. I got the point: ' . var_export($R, true));
        }

        return array('random_number' => $random_number, 'R' => $R, 'Rx_hex' => $Rx_hex, 'Ry_hex' => $Ry_hex);
    }
}